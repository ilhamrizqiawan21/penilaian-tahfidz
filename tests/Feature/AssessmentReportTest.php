<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\AssessmentReports;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class AssessmentReportTest extends TestCase
{
    use RefreshDatabase;

    private function id(): string
    {
        return (string) Str::ulid();
    }

    private function row(string $table, array $values): string
    {
        $id = $this->id();
        DB::table($table)->insert(['id' => $id, ...$values]);

        return $id;
    }

    private function fixture(): array
    {
        $owner = User::factory()->create();
        $other = User::factory()->make(['id' => 999]);
        $student = $this->row('students', ['owner_id' => $owner->id, 'code' => 'S-1', 'name' => '=Santri Uji']);
        $dataset = $this->row('quran_datasets', ['source_name' => 'Fixture sintetis F6', 'version' => 'test-only', 'riwayah' => 'Hafs', 'checksum' => str_repeat('a', 64), 'validation_status' => 'active']);
        $surah = $this->row('surahs', ['dataset_id' => $dataset, 'number' => 1, 'name_local' => 'Surah uji', 'ayah_count' => 5]);
        $ayahs = [];
        for ($number = 1; $number <= 5; $number++) {
            $ayahs[] = $this->row('ayahs', ['dataset_id' => $dataset, 'surah_id' => $surah, 'number' => $number, 'global_order' => $number]);
        }
        $edition = $this->row('mushaf_editions', ['dataset_id' => $dataset, 'name' => 'Edisi sintetis', 'version' => 'test-only', 'status' => 'active', 'checksum' => str_repeat('b', 64)]);
        $program = $this->row('programs', ['owner_id' => $owner->id, 'name' => 'Program uji']);
        $version1 = $this->row('program_versions', ['program_id' => $program, 'version' => 1, 'dataset_id' => $dataset, 'name_snapshot' => 'Target lama', 'status' => 'published']);
        $version2 = $this->row('program_versions', ['program_id' => $program, 'version' => 2, 'dataset_id' => $dataset, 'name_snapshot' => 'Target baru', 'status' => 'published']);
        $this->row('program_ranges', ['program_version_id' => $version1, 'start_ayah_id' => $ayahs[0], 'end_ayah_id' => $ayahs[2], 'sort_order' => 1]);
        $this->row('program_ranges', ['program_version_id' => $version1, 'start_ayah_id' => $ayahs[1], 'end_ayah_id' => $ayahs[3], 'sort_order' => 2]);
        $this->row('program_ranges', ['program_version_id' => $version2, 'start_ayah_id' => $ayahs[1], 'end_ayah_id' => $ayahs[4], 'sort_order' => 1]);
        $enrollment1 = $this->row('enrollments', ['student_id' => $student, 'program_id' => $program, 'program_version_id' => $version1, 'started_at' => now()->subDays(10), 'ended_at' => now()->subDay()]);
        $enrollment2 = $this->row('enrollments', ['student_id' => $student, 'program_id' => $program, 'program_version_id' => $version2, 'previous_enrollment_id' => $enrollment1, 'active_student_id' => $student, 'started_at' => now()]);
        $activity = $this->row('activity_types', ['owner_id' => $owner->id, 'name' => 'Setoran', 'counts_toward_progress' => true]);
        $rubric = $this->row('rubrics', ['owner_id' => $owner->id, 'name' => 'Rubrik']);
        $rubricVersion = $this->row('rubric_versions', ['rubric_id' => $rubric, 'version' => 1, 'name_snapshot' => 'Rubrik uji', 'status' => 'published', 'pass_threshold' => 80]);
        $criterion = $this->row('criteria', ['rubric_version_id' => $rubricVersion, 'name' => 'Kelancaran', 'method' => 'deduction', 'min_score' => 0, 'max_score' => 100, 'weight' => 100, 'sort_order' => 1]);
        $rule = $this->row('mistake_rules', ['criterion_id' => $criterion, 'name' => 'Terhenti', 'deduction_points' => 5]);

        return compact('owner', 'other', 'student', 'program', 'activity', 'rubricVersion', 'enrollment1', 'enrollment2', 'edition', 'ayahs', 'rule');
    }

    private function assessment(array $fixture, int $start, int $end, bool $passed = true, bool $counts = true, ?string $enrollment = null, ?string $record = null, int $revision = 1): array
    {
        $record ??= $this->row('assessment_records', ['owner_id' => $fixture['owner']->id, 'student_id' => $fixture['student']]);
        $id = $this->row('assessments', [
            'record_id' => $record, 'revision_number' => $revision, 'enrollment_id' => $enrollment ?? $fixture['enrollment1'],
            'activity_type_id' => $fixture['activity'], 'activity_name_snapshot' => 'Setoran', 'counts_toward_progress_snapshot' => $counts,
            'rubric_version_id' => $fixture['rubricVersion'], 'edition_id' => $fixture['edition'], 'status' => 'final',
            'assessed_at' => now(), 'finalized_at' => now(), 'lock_version' => 1, 'final_score' => $passed ? 90 : 60,
            'passed' => $passed, 'last_ayah_id' => $fixture['ayahs'][$end - 1],
        ]);
        $this->row('assessment_ranges', ['assessment_id' => $id, 'kind' => 'actual', 'start_ayah_id' => $fixture['ayahs'][$start - 1], 'end_ayah_id' => $fixture['ayahs'][$end - 1], 'sort_order' => 1]);
        DB::table('assessment_records')->where('id', $record)->update(['current_final_id' => $id]);

        return [$record, $id];
    }

    public function test_unique_progress_uses_current_final_and_enrollment_version(): void
    {
        $f = $this->fixture();
        $this->assessment($f, 1, 2);
        $this->assessment($f, 2, 3);
        $this->assessment($f, 4, 4, false);
        $this->assessment($f, 4, 4, true, false);
        $this->assessment($f, 3, 5, true, true, $f['enrollment2']);
        [$voidRecord] = $this->assessment($f, 4, 4);
        DB::table('assessment_records')->where('id', $voidRecord)->update(['current_final_id' => null, 'voided_at' => now()]);
        [$revisionRecord, $old] = $this->assessment($f, 4, 4);
        DB::table('assessments')->where('id', $old)->update(['status' => 'superseded']);
        $this->assessment($f, 4, 4, false, true, null, $revisionRecord, 2);

        $report = app(AssessmentReports::class)->profile($f['owner']->id, $f['student']);
        $this->assertSame(3, $report['progress'][1]['unique_ayahs']);
        $this->assertSame(4, $report['progress'][1]['target_ayahs']);
        $this->assertSame(75.0, $report['progress'][1]['percent']);
        $this->assertSame(3, $report['progress'][0]['unique_ayahs']);
        $this->assertSame(4, $report['progress'][0]['target_ayahs']);
        $this->assertSame(6, $report['summary']['sessions']);
        $this->assertSame(1, $report['summary']['murajaah']);
        $this->assertSame(6, app(AssessmentReports::class)->index($f['owner']->id, [])['summary']['sessions']);
        $this->assertSame(0, app(AssessmentReports::class)->index($f['other']->id, [])['summary']['sessions']);
    }

    public function test_filters_export_and_owner_boundary_use_only_visible_finals(): void
    {
        $f = $this->fixture();
        [, $first] = $this->assessment($f, 1, 2);
        $this->assessment($f, 3, 4, true, true, $f['enrollment2']);
        $this->actingAs($f['owner'])->get('/reports')->assertOk();
        $this->actingAs($f['owner'])->get("/reports/students/{$f['student']}")->assertOk();
        $this->actingAs($f['other'])->get("/reports/students/{$f['student']}")->assertNotFound();
        $this->actingAs($f['owner'])->get('/reports?program_id='.$f['program'])->assertOk();
        $filtered = app(AssessmentReports::class)->index($f['owner']->id, ['student_id' => $f['student'], 'program_id' => $f['program']]);
        $this->assertSame(2, $filtered['summary']['sessions']);
        $this->assertSame(0, app(AssessmentReports::class)->index($f['owner']->id, ['from' => now()->addDay()->toDateString()])['summary']['sessions']);
        $csv = $this->actingAs($f['owner'])->get('/reports/export?student_id='.$f['student'])->assertOk();
        $content = $csv->streamedContent();
        $this->assertStringContainsString("'=Santri Uji", $content);
        $this->assertSame(3, count(explode("\n", trim($content))));
        $this->actingAs($f['other'])->get('/reports/export')->assertOk()->assertDontSee('Santri Uji');
        $this->actingAs($f['owner'])->get('/reports?from=2026-09-22&to=2026-09-21')->assertSessionHasErrors('to');
        $this->assertNotEmpty($first);
    }

    public function test_mistake_counts_events_and_distinct_sessions_without_retracted_or_superseded(): void
    {
        $f = $this->fixture();
        [, $first] = $this->assessment($f, 1, 2);
        [, $second] = $this->assessment($f, 1, 2);
        foreach ([$first, $first, $second] as $assessment) {
            $this->row('annotations', ['assessment_id' => $assessment, 'client_event_id' => $this->id(), 'ayah_id' => $f['ayahs'][0], 'kind' => 'penalty', 'mistake_rule_id' => $f['rule']]);
        }
        $this->row('annotations', ['assessment_id' => $second, 'client_event_id' => $this->id(), 'ayah_id' => $f['ayahs'][0], 'kind' => 'penalty', 'mistake_rule_id' => $f['rule'], 'retracted_at' => now()]);
        [$record, $superseded] = $this->assessment($f, 1, 2);
        $this->row('annotations', ['assessment_id' => $superseded, 'client_event_id' => $this->id(), 'ayah_id' => $f['ayahs'][0], 'kind' => 'penalty', 'mistake_rule_id' => $f['rule']]);
        DB::table('assessments')->where('id', $superseded)->update(['status' => 'superseded']);
        $this->assessment($f, 1, 2, true, true, null, $record, 2);
        $mistake = app(AssessmentReports::class)->profile($f['owner']->id, $f['student'])['mistakes'][0];
        $this->assertSame(3, (int) $mistake['event_count']);
        $this->assertSame(2, (int) $mistake['session_count']);
    }
}
