<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class AssessmentDraftTest extends TestCase
{
    use RefreshDatabase;

    private function fixture(string $editionStatus = 'active'): array
    {
        $owner = User::factory()->create();
        $this->actingAs($owner)->post('/students', ['code' => 'S-01', 'name' => 'Santri uji']);
        $this->actingAs($owner)->post('/activity-types', ['name' => 'Setoran', 'counts_toward_progress' => true]);
        $this->actingAs($owner)->post('/rubrics', ['name' => 'Rubrik uji', 'pass_threshold' => '80', 'criteria' => [
            ['name' => 'Kelancaran', 'method' => 'deduction', 'min_score' => '0', 'max_score' => '100', 'weight' => '60', 'rules' => [['name' => 'Terhenti', 'deduction_points' => '5']]],
            ['name' => 'Fashahah', 'method' => 'direct', 'min_score' => '0', 'max_score' => '100', 'weight' => '40', 'rules' => []],
        ], 'bands' => []]);
        $rubric = DB::table('rubrics')->sole();
        $version = DB::table('rubric_versions')->sole();
        $this->actingAs($owner)->post("/rubrics/{$rubric->id}/versions/{$version->id}/publish");

        $datasetId = (string) Str::ulid();
        $surahId = (string) Str::ulid();
        DB::table('quran_datasets')->insert(['id' => $datasetId, 'source_name' => 'Fixture sintetis F5', 'version' => 'test-only', 'riwayah' => 'Hafs', 'checksum' => str_repeat('a', 64), 'validation_status' => 'active', 'created_at' => now(), 'updated_at' => now()]);
        DB::table('surahs')->insert(['id' => $surahId, 'dataset_id' => $datasetId, 'number' => 1, 'name_local' => 'Surah uji', 'ayah_count' => 3]);
        $ayahs = [];
        for ($number = 1; $number <= 3; $number++) {
            $id = (string) Str::ulid();
            DB::table('ayahs')->insert(['id' => $id, 'dataset_id' => $datasetId, 'surah_id' => $surahId, 'number' => $number, 'global_order' => $number, 'text_uthmani' => "Teks sintetis $number"]);
            $ayahs[] = $id;
        }
        $editionId = (string) Str::ulid();
        DB::table('mushaf_editions')->insert(['id' => $editionId, 'dataset_id' => $datasetId, 'name' => 'Edisi sintetis', 'version' => 'test-only', 'status' => $editionStatus, 'checksum' => str_repeat('b', 64), 'created_at' => now(), 'updated_at' => now()]);
        $wordId = (string) Str::ulid();
        DB::table('edition_words')->insert(['id' => $wordId, 'edition_id' => $editionId, 'ayah_id' => $ayahs[0], 'position' => 1, 'source_word_key' => 'test-1', 'text_or_glyph' => 'kata-uji', 'token_kind' => 'word']);

        return [$owner, DB::table('students')->sole(), DB::table('activity_types')->sole(), $version, DB::table('criteria')->where('rubric_version_id', $version->id)->orderBy('sort_order')->get(), DB::table('mistake_rules')->sole(), $editionId, $ayahs, $wordId];
    }

    private function range(int $start, int $end): array
    {
        return ['startSurah' => 1, 'startAyah' => $start, 'endSurah' => 1, 'endAyah' => $end];
    }

    private function createPayload(object $student, object $activity, object $version, string $editionId): array
    {
        return ['student_id' => $student->id, 'activity_type_id' => $activity->id, 'rubric_version_id' => $version->id, 'edition_id' => $editionId, 'planned_ranges' => [$this->range(1, 3)], 'mutation_id' => (string) Str::ulid()];
    }

    public function test_create_requires_active_edition_and_snapshots_configuration(): void
    {
        [$owner, $student, $activity, $version, , , $editionId] = $this->fixture('validated');
        $this->actingAs($owner)->postJson('/assessments', $this->createPayload($student, $activity, $version, $editionId))->assertUnprocessable()->assertJsonValidationErrors('edition_id');
        DB::table('mushaf_editions')->where('id', $editionId)->update(['status' => 'active']);
        $created = $this->actingAs($owner)->postJson('/assessments', $this->createPayload($student, $activity, $version, $editionId))->assertCreated()->assertJsonPath('lock_version', 0);
        $draft = DB::table('assessments')->where('id', $created->json('id'))->sole();
        $this->assertSame('draft', $draft->status);
        $this->assertSame('Setoran', $draft->activity_name_snapshot);
        $this->assertSame(1, $draft->counts_toward_progress_snapshot);
        $this->assertDatabaseCount('assessment_ranges', 1);
        $this->assertDatabaseCount('criterion_scores', 2);
        $this->actingAs($owner)->get('/assessments')->assertOk();
        $this->actingAs($owner)->get("/assessments/{$draft->id}/work")->assertOk();
        $this->actingAs($owner)->getJson("/assessments/{$draft->id}")->assertOk()
            ->assertJsonPath('status', 'draft')->assertJsonPath('student.name', 'Santri uji')
            ->assertJsonPath('ayahs.0.number', 1)->assertJsonPath('ayahs.0.words.0.text_or_glyph', 'kata-uji');
        $other = User::factory()->make(['id' => 999]);
        $this->actingAs($other)->getJson("/assessments/{$draft->id}")->assertNotFound();
    }

    public function test_create_retry_returns_same_draft_and_rejects_changed_payload(): void
    {
        [$owner, $student, $activity, $version, , , $editionId] = $this->fixture();
        $payload = $this->createPayload($student, $activity, $version, $editionId);
        $first = $this->actingAs($owner)->postJson('/assessments', $payload)->assertCreated();
        $this->actingAs($owner)->postJson('/assessments', $payload)->assertCreated()->assertJsonPath('id', $first->json('id'));
        $this->actingAs($owner)->postJson('/assessments', [...$payload, 'planned_ranges' => [$this->range(1, 2)]])->assertConflict();
        $this->assertDatabaseCount('assessment_records', 1);
        $this->assertDatabaseCount('assessments', 1);
    }

    public function test_autosave_is_idempotent_and_rejects_stale_or_changed_retry(): void
    {
        [$owner, $student, $activity, $version, $criteria, $rule, $editionId, $ayahs, $wordId] = $this->fixture();
        $id = $this->actingAs($owner)->postJson('/assessments', $this->createPayload($student, $activity, $version, $editionId))->json('id');
        $payload = ['lock_version' => 0, 'mutation_id' => (string) Str::ulid(), 'direct' => [$criteria[1]->id => '90'], 'actual_ranges' => [$this->range(1, 2)], 'events' => [
            ['id' => (string) Str::ulid(), 'kind' => 'penalty', 'ayah_id' => $ayahs[0], 'edition_word_id' => $wordId, 'rule_id' => $rule->id, 'active' => true],
            ['id' => (string) Str::ulid(), 'kind' => 'note', 'ayah_id' => $ayahs[1], 'note' => 'Catatan uji', 'active' => true],
        ]];
        $this->actingAs($owner)->putJson("/assessments/$id/draft", $payload)->assertOk()->assertJsonPath('lock_version', 1);
        $this->actingAs($owner)->putJson("/assessments/$id/draft", $payload)->assertOk()->assertJsonPath('lock_version', 1);
        $this->assertDatabaseCount('annotations', 2);
        $this->actingAs($owner)->putJson("/assessments/$id/draft", [...$payload, 'direct' => [$criteria[1]->id => '91']])->assertConflict();
        $this->actingAs($owner)->putJson("/assessments/$id/draft", [...$payload, 'mutation_id' => (string) Str::ulid()])->assertConflict();
        $this->assertSame(1, DB::table('assessments')->where('id', $id)->value('lock_version'));
    }

    public function test_finalization_validates_coverage_and_keeps_result_on_retry(): void
    {
        [$owner, $student, $activity, $version, $criteria, $rule, $editionId, $ayahs] = $this->fixture();
        $created = $this->actingAs($owner)->postJson('/assessments', $this->createPayload($student, $activity, $version, $editionId))->assertCreated();
        $id = $created->json('id');
        $this->actingAs($owner)->postJson("/assessments/$id/finalize", ['lock_version' => 0, 'mutation_id' => (string) Str::ulid()])->assertUnprocessable()->assertJsonValidationErrors('actual_ranges');
        $save = ['lock_version' => 0, 'mutation_id' => (string) Str::ulid(), 'direct' => [$criteria[1]->id => '90'], 'actual_ranges' => [$this->range(1, 1)], 'events' => [['id' => (string) Str::ulid(), 'kind' => 'penalty', 'ayah_id' => $ayahs[1], 'rule_id' => $rule->id, 'active' => true]]];
        $this->actingAs($owner)->putJson("/assessments/$id/draft", $save)->assertUnprocessable()->assertJsonValidationErrors('events.0.ayah_id');
        $save['events'][0]['ayah_id'] = $ayahs[0];
        $this->actingAs($owner)->putJson("/assessments/$id/draft", $save)->assertOk();
        $mutationId = (string) Str::ulid();
        $final = $this->actingAs($owner)->postJson("/assessments/$id/finalize", ['lock_version' => 1, 'mutation_id' => $mutationId])->assertOk()->assertJsonPath('display_score', '93.00');
        $this->assertSame('final', DB::table('assessments')->where('id', $id)->value('status'));
        $this->assertSame($id, DB::table('assessment_records')->where('id', $created->json('record_id'))->value('current_final_id'));
        $this->actingAs($owner)->postJson("/assessments/$id/finalize", ['lock_version' => 1, 'mutation_id' => $mutationId])->assertOk()->assertJsonPath('display_score', '93.00');
        $this->assertSame($final->json('unrounded_score'), DB::table('assessments')->where('id', $id)->value('final_score'));
        $this->assertDatabaseCount('assessments', 1);
    }

    public function test_revision_cancel_swap_and_void_keep_audit_history(): void
    {
        [$owner, $student, $activity, $version, $criteria, , $editionId] = $this->fixture();
        $created = $this->actingAs($owner)->postJson('/assessments', $this->createPayload($student, $activity, $version, $editionId))->assertCreated();
        $id = $created->json('id');
        $record = $created->json('record_id');
        $this->actingAs($owner)->putJson("/assessments/$id/draft", ['lock_version' => 0, 'mutation_id' => (string) Str::ulid(), 'direct' => [$criteria[1]->id => '90'], 'actual_ranges' => [$this->range(1, 2)], 'events' => []])->assertOk();
        $this->actingAs($owner)->postJson("/assessments/$id/finalize", ['lock_version' => 1, 'mutation_id' => (string) Str::ulid()])->assertOk();
        $revision = $this->actingAs($owner)->postJson("/assessment-records/$record/revisions", ['reason' => 'Perbaikan pemeriksaan'])->assertCreated();
        $this->assertSame($id, DB::table('assessment_records')->where('id', $record)->value('current_final_id'));
        $this->actingAs($owner)->postJson('/assessments/'.$revision->json('id').'/cancel', [])->assertOk();
        $revision = $this->actingAs($owner)->postJson("/assessment-records/$record/revisions", ['reason' => 'Koreksi nilai'])->assertCreated();
        $revisionId = $revision->json('id');
        $this->actingAs($owner)->putJson("/assessments/$revisionId/draft", ['lock_version' => 0, 'mutation_id' => (string) Str::ulid(), 'direct' => [$criteria[1]->id => '100'], 'actual_ranges' => [$this->range(1, 2)], 'events' => []])->assertOk();
        $this->actingAs($owner)->postJson("/assessments/$revisionId/finalize", ['lock_version' => 1, 'mutation_id' => (string) Str::ulid()])->assertOk();
        $this->assertSame('superseded', DB::table('assessments')->where('id', $id)->value('status'));
        $this->assertSame($revisionId, DB::table('assessment_records')->where('id', $record)->value('current_final_id'));
        $this->actingAs($owner)->postJson("/assessment-records/$record/void", [])->assertUnprocessable();
        $this->actingAs($owner)->postJson("/assessment-records/$record/void", ['reason' => 'Dibatalkan guru'])->assertOk();
        $this->assertNotNull(DB::table('assessment_records')->where('id', $record)->value('voided_at'));
        $this->assertGreaterThanOrEqual(4, DB::table('audit_events')->count());
    }

    public function test_undo_keeps_annotation_history_and_reusing_event_id_cannot_reactivate_it(): void
    {
        [$owner, $student, $activity, $version, $criteria, $rule, $editionId, $ayahs] = $this->fixture();
        $id = $this->actingAs($owner)->postJson('/assessments', $this->createPayload($student, $activity, $version, $editionId))->json('id');
        $event = ['id' => (string) Str::ulid(), 'kind' => 'penalty', 'ayah_id' => $ayahs[0], 'rule_id' => $rule->id, 'active' => true];
        $save = ['lock_version' => 0, 'mutation_id' => (string) Str::ulid(), 'direct' => [$criteria[1]->id => '90'], 'actual_ranges' => [$this->range(1, 1)], 'events' => [$event]];
        $this->actingAs($owner)->putJson("/assessments/$id/draft", $save)->assertOk();
        $undone = [...$save, 'lock_version' => 1, 'mutation_id' => (string) Str::ulid(), 'events' => [[...$event, 'active' => false]]];
        $this->actingAs($owner)->putJson("/assessments/$id/draft", $undone)->assertOk()->assertJsonPath('preview.display_score', '96.00');
        $this->assertNotNull(DB::table('annotations')->where('assessment_id', $id)->value('retracted_at'));
        $this->actingAs($owner)->putJson("/assessments/$id/draft", [...$save, 'lock_version' => 2, 'mutation_id' => (string) Str::ulid()])->assertConflict();
        $this->assertDatabaseCount('annotations', 1);
    }
}
