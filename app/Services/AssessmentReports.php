<?php

namespace App\Services;

use App\Support\Numbers;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

class AssessmentReports
{
    public function finals(int $ownerId, array $filters): Builder
    {
        $query = DB::table('assessment_records as r')
            ->join('assessments as a', 'a.id', '=', 'r.current_final_id')
            ->join('students as s', 's.id', '=', 'r.student_id')
            ->join('rubric_versions as rv', 'rv.id', '=', 'a.rubric_version_id')
            ->leftJoin('enrollments as e', 'e.id', '=', 'a.enrollment_id')
            ->leftJoin('program_versions as pv', 'pv.id', '=', 'e.program_version_id')
            ->where('r.owner_id', $ownerId)->whereNull('r.voided_at')->where('a.status', 'final');

        foreach (['student_id' => 'r.student_id', 'program_id' => 'e.program_id', 'activity_type_id' => 'a.activity_type_id', 'rubric_version_id' => 'a.rubric_version_id'] as $key => $column) {
            if (! empty($filters[$key])) {
                $query->where($column, $filters[$key]);
            }
        }
        if (! empty($filters['from'])) {
            $query->whereDate('a.assessed_at', '>=', $filters['from']);
        }
        if (! empty($filters['to'])) {
            $query->whereDate('a.assessed_at', '<=', $filters['to']);
        }

        return $query;
    }

    public function index(int $ownerId, array $filters): array
    {
        $query = $this->finals($ownerId, $filters);
        $summary = (clone $query)->selectRaw('COUNT(*) as sessions, SUM(CASE WHEN a.passed = 1 THEN 1 ELSE 0 END) as passed, SUM(CASE WHEN a.counts_toward_progress_snapshot = 0 THEN 1 ELSE 0 END) as murajaah')->first();
        $rows = $this->selectRows(clone $query)->orderByDesc('a.assessed_at')->orderByDesc('a.id')->paginate(30)->withQueryString()
            ->through(fn ($row) => $this->formatFinalScore($row));

        return [
            'summary' => ['sessions' => (int) $summary->sessions, 'passed' => (int) $summary->passed, 'murajaah' => (int) $summary->murajaah],
            'rows' => $rows,
        ];
    }

    public function selectRows(Builder $query): Builder
    {
        return $query->select([
            'a.id', 'a.record_id', 'a.assessed_at', 'a.final_score', 'a.passed', 'a.grade_label_snapshot',
            'a.activity_name_snapshot', 'a.counts_toward_progress_snapshot', 'a.rubric_version_id',
            'rv.name_snapshot as rubric_name', 'rv.version as rubric_version',
            's.id as student_id', 's.name as student_name', 's.code as student_code',
            'pv.name_snapshot as program_name', 'pv.version as program_version',
        ]);
    }

    public function profile(int $ownerId, string $studentId): array
    {
        $student = DB::table('students')->where('id', $studentId)->where('owner_id', $ownerId)->first(['id', 'name', 'code', 'archived_at']) ?? abort(404);
        $enrollments = DB::table('enrollments as e')
            ->join('programs as p', 'p.id', '=', 'e.program_id')
            ->join('program_versions as pv', 'pv.id', '=', 'e.program_version_id')
            ->where('e.student_id', $studentId)->where('p.owner_id', $ownerId)
            ->orderByDesc('e.started_at')->get(['e.id', 'e.program_version_id', 'e.started_at', 'e.ended_at', 'p.name as program_name', 'pv.version', 'pv.name_snapshot as version_name']);
        $versionIds = $enrollments->pluck('program_version_id')->unique()->all();
        $targets = DB::table('program_ranges as pr')->join('ayahs as first', 'first.id', '=', 'pr.start_ayah_id')
            ->join('ayahs as last', 'last.id', '=', 'pr.end_ayah_id')
            ->whereIn('pr.program_version_id', $versionIds)
            ->get(['pr.program_version_id', 'first.global_order as start_order', 'last.global_order as end_order'])
            ->groupBy('program_version_id');
        $base = $this->finals($ownerId, ['student_id' => $studentId]);
        $summary = (clone $base)->selectRaw('COUNT(*) as sessions, SUM(CASE WHEN a.counts_toward_progress_snapshot = 0 THEN 1 ELSE 0 END) as murajaah')->first();
        $last = (clone $base)->orderByDesc('a.assessed_at')->orderByDesc('a.id')->first(['a.last_ayah_id']);
        $sessionsByEnrollment = (clone $base)->whereNotNull('a.enrollment_id')->groupBy('a.enrollment_id')
            ->selectRaw('a.enrollment_id, COUNT(*) as sessions')->pluck('sessions', 'enrollment_id');
        $eligible = (clone $base)->where('a.passed', true)->where('a.counts_toward_progress_snapshot', true)
            ->whereNotNull('a.enrollment_id')->get(['a.id', 'a.enrollment_id']);
        $eligibleByEnrollment = $eligible->groupBy('enrollment_id');
        $actual = DB::table('assessment_ranges as ar')->join('ayahs as first', 'first.id', '=', 'ar.start_ayah_id')
            ->join('ayahs as last', 'last.id', '=', 'ar.end_ayah_id')
            ->whereIn('ar.assessment_id', $eligible->pluck('id')->all())->where('ar.kind', 'actual')
            ->get(['ar.assessment_id', 'first.global_order as start_order', 'last.global_order as end_order'])
            ->groupBy('assessment_id');

        $progress = $enrollments->map(function ($enrollment) use ($targets, $eligibleByEnrollment, $actual, $sessionsByEnrollment) {
            $target = $targets->get($enrollment->program_version_id, collect())->map(fn ($range) => [(int) $range->start_order, (int) $range->end_order])->all();
            $earned = [];
            foreach ($eligibleByEnrollment->get($enrollment->id, collect()) as $assessment) {
                foreach ($actual->get($assessment->id, collect()) as $range) {
                    foreach ($target as [$start, $end]) {
                        $first = max($start, (int) $range->start_order);
                        $last = min($end, (int) $range->end_order);
                        if ($first <= $last) {
                            $earned[] = [$first, $last];
                        }
                    }
                }
            }
            $denominator = $this->uniqueCount($target);
            $numerator = $this->uniqueCount($earned);

            return [
                'id' => $enrollment->id, 'program_name' => $enrollment->program_name,
                'version' => $enrollment->version, 'version_name' => $enrollment->version_name,
                'active' => $enrollment->ended_at === null,
                'unique_ayahs' => $numerator, 'target_ayahs' => $denominator,
                'percent' => $denominator ? round($numerator * 100 / $denominator, 1) : 0,
                'sessions' => (int) ($sessionsByEnrollment[$enrollment->id] ?? 0),
            ];
        })->all();

        $lastAyah = $last?->last_ayah_id ? DB::table('ayahs as a')->join('surahs as s', 's.id', '=', 'a.surah_id')
            ->where('a.id', $last->last_ayah_id)->first(['s.name_local as surah', 's.number as surah_number', 'a.number as ayah_number']) : null;
        $mistakes = DB::table('annotations as an')->join('ayahs as ay', 'ay.id', '=', 'an.ayah_id')
            ->join('surahs as su', 'su.id', '=', 'ay.surah_id')
            ->join('mistake_rules as mr', 'mr.id', '=', 'an.mistake_rule_id')
            ->whereIn('an.assessment_id', (clone $base)->select('a.id'))->where('an.kind', 'penalty')->whereNull('an.retracted_at')
            ->groupBy('an.ayah_id', 'an.mistake_rule_id', 'su.name_local', 'su.number', 'ay.number', 'mr.name')
            ->orderByDesc('session_count')->limit(20)
            ->select(['su.name_local as surah', 'su.number as surah_number', 'ay.number as ayah_number', 'mr.name as rule_name'])
            ->selectRaw('COUNT(*) as event_count, COUNT(DISTINCT an.assessment_id) as session_count')->get()
            ->map(fn ($row) => (array) $row)->all();
        $history = $this->selectRows($this->finals($ownerId, ['student_id' => $studentId]))
            ->orderByDesc('a.assessed_at')->orderByDesc('a.id')->paginate(20)->withQueryString()
            ->through(fn ($row) => $this->formatFinalScore($row));

        return [
            'student' => $student, 'progress' => $progress,
            'summary' => ['sessions' => (int) $summary->sessions, 'murajaah' => (int) $summary->murajaah, 'last_ayah' => $lastAyah],
            'mistakes' => $mistakes, 'history' => $history,
        ];
    }

    private function formatFinalScore(object $row): object
    {
        $row->final_score = $row->final_score !== null ? Numbers::trim(bcadd((string) $row->final_score, '0.005', 2)) : null;

        return $row;
    }

    public function uniqueCount(array $ranges): int
    {
        usort($ranges, fn ($a, $b) => $a[0] <=> $b[0]);
        $total = 0;
        $end = 0;
        foreach ($ranges as [$start, $last]) {
            $total += max(0, $last - max($end, $start - 1));
            $end = max($end, $last);
        }

        return $total;
    }
}
