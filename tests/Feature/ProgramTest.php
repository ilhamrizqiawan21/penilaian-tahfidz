<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class ProgramTest extends TestCase
{
    use RefreshDatabase;

    private function reference(string $status = 'active'): array
    {
        $dataset = (string) Str::ulid();
        DB::table('quran_datasets')->insert(['id' => $dataset, 'source_name' => 'Fixture sintetis', 'version' => 'test-only', 'riwayah' => 'Hafs', 'checksum' => str_repeat('a', 64), 'validation_status' => $status, 'created_at' => now(), 'updated_at' => now()]);
        $ayahs = [];
        $order = 0;
        foreach ([1 => 3, 2 => 2] as $surahNumber => $count) {
            $surah = (string) Str::ulid();
            DB::table('surahs')->insert(['id' => $surah, 'dataset_id' => $dataset, 'number' => $surahNumber, 'name_local' => "Surah uji $surahNumber", 'ayah_count' => $count]);
            for ($ayahNumber = 1; $ayahNumber <= $count; $ayahNumber++) {
                $id = (string) Str::ulid();
                DB::table('ayahs')->insert(['id' => $id, 'dataset_id' => $dataset, 'surah_id' => $surah, 'number' => $ayahNumber, 'global_order' => ++$order]);
                $ayahs["$surahNumber:$ayahNumber"] = $id;
            }
        }
        foreach ([1 => ['1:1', '1:3'], 2 => ['2:1', '2:2']] as $juz => [$start, $end]) {
            DB::table('juz_ranges')->insert(['id' => (string) Str::ulid(), 'dataset_id' => $dataset, 'juz_number' => $juz, 'start_ayah_id' => $ayahs[$start], 'end_ayah_id' => $ayahs[$end]]);
        }

        return [$dataset, $ayahs];
    }

    private function range(int $startSurah, int $startAyah, int $endSurah, int $endAyah): array
    {
        return compact('startSurah', 'startAyah', 'endSurah', 'endAyah');
    }

    public function test_program_requires_an_active_reference_dataset(): void
    {
        $owner = User::factory()->create();
        $this->actingAs($owner)->get('/programs')->assertOk()->assertInertia(fn (Assert $page) => $page->component('Programs/Index')->where('reference', null)->has('programs', 0)->etc());
        $this->actingAs($owner)->post('/programs', ['name' => 'Hafalan', 'ranges' => [$this->range(1, 1, 1, 2)]])->assertSessionHasErrors('dataset');
        $this->assertDatabaseCount('programs', 0);
        $this->reference('imported');
        $this->actingAs($owner)->post('/programs', ['name' => 'Hafalan', 'ranges' => [$this->range(1, 1, 1, 2)]])->assertSessionHasErrors('dataset');
        $this->assertDatabaseCount('programs', 0);
    }

    public function test_preview_unions_overlap_and_accepts_cross_surah_ranges(): void
    {
        $owner = User::factory()->create();
        $this->reference();
        $this->actingAs($owner)->postJson('/programs/preview', ['ranges' => [
            $this->range(1, 2, 2, 1),
            $this->range(1, 1, 1, 3),
        ]])->assertOk()->assertJsonPath('unique_ayahs', 4)->assertJsonPath('ranges.0.count', 3);
        $this->actingAs($owner)->postJson('/programs/preview', ['ranges' => [$this->range(2, 2, 1, 1)]])->assertUnprocessable()->assertJsonValidationErrors('ranges.0');
        $this->actingAs($owner)->postJson('/programs/preview', ['ranges' => [$this->range(1, 1, 1, 4)]])->assertUnprocessable()->assertJsonValidationErrors('ranges.0');
        $this->actingAs($owner)->postJson('/programs/preview', ['ranges' => [$this->range(1, 1, 1, 1), $this->range(1, 1, 1, 1)]])->assertOk()->assertJsonPath('unique_ayahs', 1);
    }

    public function test_published_version_is_immutable_and_new_version_preserves_old_target(): void
    {
        $owner = User::factory()->create();
        $this->reference();
        $this->actingAs($owner)->post('/programs', ['name' => 'Hafalan awal', 'ranges' => [$this->range(1, 1, 1, 2)]])->assertRedirect('/programs');
        $program = DB::table('programs')->sole();
        $first = DB::table('program_versions')->sole();
        $this->actingAs($owner)->post("/programs/{$program->id}/versions/{$first->id}/publish")->assertRedirect('/programs');
        $this->actingAs($owner)->put("/programs/{$program->id}/versions/{$first->id}", ['name' => 'Mengubah final', 'ranges' => [$this->range(2, 1, 2, 2)]])->assertStatus(409);
        $this->actingAs($owner)->post("/programs/{$program->id}/versions")->assertRedirect('/programs');
        $second = DB::table('program_versions')->where('program_id', $program->id)->where('version', 2)->first();
        $this->actingAs($owner)->put("/programs/{$program->id}/versions/{$second->id}", ['name' => 'Hafalan lanjut', 'ranges' => [$this->range(1, 3, 2, 2)]])->assertRedirect('/programs');
        $this->actingAs($owner)->post("/programs/{$program->id}/versions/{$second->id}/publish")->assertRedirect('/programs');
        $this->actingAs($owner)->get('/programs')->assertOk()->assertInertia(fn (Assert $page) => $page->component('Programs/Index')->has('programs', 1)->has('programs.0.versions', 2)->has('programs.0.versions.0.ranges', 1)->etc());
        $this->assertSame('retired', DB::table('program_versions')->where('id', $first->id)->value('status'));
        $this->assertSame('published', DB::table('program_versions')->where('id', $second->id)->value('status'));
        $this->assertSame('Hafalan awal', DB::table('program_versions')->where('id', $first->id)->value('name_snapshot'));
        $this->assertSame(2, DB::table('program_ranges')->where('program_version_id', $first->id)->join('ayahs as ends', 'ends.id', '=', 'program_ranges.end_ayah_id')->value('ends.global_order'));
        $this->assertSame(5, DB::table('program_ranges')->where('program_version_id', $second->id)->join('ayahs as ends', 'ends.id', '=', 'program_ranges.end_ayah_id')->value('ends.global_order'));
    }

    public function test_enrollment_moves_explicitly_and_foreign_owner_cannot_mutate(): void
    {
        $owner = User::factory()->create();
        $this->reference();
        $this->actingAs($owner)->post('/students', ['code' => 'E-01', 'name' => 'Eman']);
        $student = DB::table('students')->sole();
        $this->actingAs($owner)->post('/programs', ['name' => 'Program', 'ranges' => [$this->range(1, 1, 1, 2)]]);
        $program = DB::table('programs')->sole();
        $first = DB::table('program_versions')->sole();
        $this->actingAs($owner)->post("/programs/{$program->id}/versions/{$first->id}/publish");
        $this->actingAs($owner)->post("/programs/{$program->id}/enrollments", ['student_id' => $student->id, 'version_id' => $first->id])->assertRedirect('/programs');
        $oldEnrollment = DB::table('enrollments')->sole();
        $this->actingAs($owner)->post("/programs/{$program->id}/enrollments", ['student_id' => $student->id, 'version_id' => $first->id])->assertSessionHasErrors('student_id');
        $this->actingAs($owner)->post("/programs/{$program->id}/versions");
        $second = DB::table('program_versions')->where('version', 2)->first();
        $this->actingAs($owner)->post("/programs/{$program->id}/versions/{$second->id}/publish");
        $this->assertNull(DB::table('enrollments')->where('id', $oldEnrollment->id)->value('ended_at'));
        $this->actingAs($owner)->post("/programs/{$program->id}/enrollments/{$oldEnrollment->id}/transfer", ['version_id' => $second->id])->assertRedirect('/programs');
        $this->assertNotNull(DB::table('enrollments')->where('id', $oldEnrollment->id)->value('ended_at'));
        $newEnrollment = DB::table('enrollments')->whereNull('ended_at')->sole();
        $this->assertSame($oldEnrollment->id, $newEnrollment->previous_enrollment_id);
        $this->assertSame($second->id, $newEnrollment->program_version_id);
        $other = User::factory()->make(['id' => 999]);
        $this->actingAs($other)->get("/programs/{$program->id}")->assertNotFound();
        $this->actingAs($other)->post("/programs/{$program->id}/versions")->assertNotFound();
        $this->actingAs($other)->post("/programs/{$program->id}/enrollments", ['student_id' => $student->id, 'version_id' => $second->id])->assertNotFound();
    }

    public function test_archiving_preserves_versions_and_enrollment_history(): void
    {
        $owner = User::factory()->create();
        $this->reference();
        $this->actingAs($owner)->post('/students', ['code' => 'A-01', 'name' => 'Amin']);
        $student = DB::table('students')->sole();
        $this->actingAs($owner)->post('/programs', ['name' => 'Program arsip', 'ranges' => [$this->range(1, 1, 1, 2)]]);
        $program = DB::table('programs')->sole();
        $version = DB::table('program_versions')->sole();
        $this->actingAs($owner)->post("/programs/{$program->id}/versions/{$version->id}/publish");
        $this->actingAs($owner)->post("/programs/{$program->id}/enrollments", ['student_id' => $student->id, 'version_id' => $version->id]);
        $this->actingAs($owner)->post("/programs/{$program->id}/archive")->assertRedirect('/programs');
        $this->assertNotNull(DB::table('programs')->where('id', $program->id)->value('archived_at'));
        $this->assertDatabaseCount('program_versions', 1);
        $this->assertDatabaseCount('enrollments', 1);
        $this->actingAs($owner)->post("/programs/{$program->id}/versions")->assertNotFound();
    }
}
