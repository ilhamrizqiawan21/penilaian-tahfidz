<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\AssessmentReports;
use Database\Seeders\DemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;
use RuntimeException;
use Tests\TestCase;

class DemoSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_demo_is_connected_and_repeat_does_not_overwrite_user_edits(): void
    {
        $owner = User::factory()->create(['email' => 'demo@penilaian-tahfidz.test']);
        $this->seed(DemoSeeder::class);
        $this->assertDatabaseCount('students', 12);
        $this->assertDatabaseCount('study_groups', 3);
        $this->assertDatabaseCount('rubrics', 3);
        $this->assertDatabaseCount('programs', 2);
        $this->assertDatabaseCount('ayahs', 24);
        $this->assertDatabaseCount('edition_words', 96);
        $this->assertGreaterThan(0, DB::table('assessments')->where('status', 'draft')->count());
        $this->assertGreaterThan(0, DB::table('assessments')->where('status', 'superseded')->count());
        $this->assertGreaterThan(0, DB::table('assessments')->where('status', 'final')->where('passed', false)->count());
        $this->assertGreaterThan(0, DB::table('assessment_records')->whereNotNull('voided_at')->count());
        $this->assertSame(0, DB::table('students')->where('owner_id', '!=', $owner->id)->count());
        $student = DB::table('students')->where('code', 'DEMO-001')->first();
        $profile = app(AssessmentReports::class)->profile($owner->id, $student->id);
        $this->assertGreaterThan(0, $profile['summary']['sessions']);
        $this->assertGreaterThan(0, collect($profile['progress'])->sum('unique_ayahs'));
        $this->actingAs($owner)->get('/dashboard')->assertInertia(fn (Assert $page) => $page->where('demoMode', true)->etc());
        $this->actingAs($owner)->get('/programs')->assertOk();
        $this->actingAs($owner)->get('/assessments')->assertOk();
        $this->actingAs($owner)->get('/reports')->assertOk();
        $count = DB::table('assessments')->count();
        DB::table('students')->where('id', $student->id)->update(['name' => 'Sudah diubah pengguna']);
        $this->seed(DemoSeeder::class);
        $this->assertDatabaseCount('students', 12);
        $this->assertDatabaseCount('assessments', $count);
        $this->assertDatabaseHas('students', ['id' => $student->id, 'name' => 'Sudah diubah pengguna']);
    }

    public function test_refuses_existing_data_without_touching_it(): void
    {
        $owner = User::factory()->create(['email' => 'demo@penilaian-tahfidz.test']);
        $this->actingAs($owner)->post('/students', ['code' => 'REAL-01', 'name' => 'Data sebelumnya']);
        try {
            $this->seed(DemoSeeder::class);
            $this->fail('Seed should refuse a populated installation.');
        } catch (RuntimeException $exception) {
            $this->assertStringContainsString('tidak kosong', $exception->getMessage());
        }
        $this->assertDatabaseCount('students', 1);
        $this->assertDatabaseCount('quran_datasets', 0);
    }

    public function test_refuses_production_and_does_not_create_an_account(): void
    {
        config(['app.env' => 'production']);
        try {
            $this->seed(DemoSeeder::class);
            $this->fail('Production must be refused.');
        } catch (RuntimeException $exception) {
            $this->assertStringContainsString('local/testing', $exception->getMessage());
        }
        $this->assertDatabaseCount('users', 0);
        $this->assertDatabaseCount('quran_datasets', 0);
    }
}
