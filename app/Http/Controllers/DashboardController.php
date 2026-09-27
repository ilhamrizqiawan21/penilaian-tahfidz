<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function __invoke(Request $request): Response
    {
        $ownerId = $request->user()->id;
        $students = DB::table('students')->where('owner_id', $ownerId)->whereNull('archived_at')->count();
        $activities = DB::table('activity_types')->where('owner_id', $ownerId)->whereNull('archived_at')->count();
        $publishedRubrics = DB::table('rubric_versions as v')->join('rubrics as r', 'r.id', '=', 'v.rubric_id')
            ->where('r.owner_id', $ownerId)->whereNull('r.archived_at')->where('v.status', 'published')->count();
        $publishedPrograms = DB::table('program_versions as v')->join('programs as p', 'p.id', '=', 'v.program_id')
            ->where('p.owner_id', $ownerId)->whereNull('p.archived_at')->where('v.status', 'published')->count();
        $referenceReady = DB::table('mushaf_editions as e')->join('quran_datasets as d', 'd.id', '=', 'e.dataset_id')
            ->where('e.status', 'active')->where('d.validation_status', 'active')->exists();
        $activeDrafts = DB::table('assessments as a')->join('assessment_records as r', 'r.id', '=', 'a.record_id')
            ->where('r.owner_id', $ownerId)->where('a.status', 'draft')->count();
        $finalAssessments = DB::table('assessment_records')->where('owner_id', $ownerId)
            ->whereNull('voided_at')->whereNotNull('current_final_id')->count();

        $recent = DB::table('assessment_records as r')->join('students as s', 's.id', '=', 'r.student_id')
            ->leftJoin('assessments as d', function ($join) {
                $join->on('d.active_draft_record_id', '=', 'r.id')->where('d.status', '=', 'draft');
            })
            ->leftJoin('assessments as f', 'f.id', '=', 'r.current_final_id')
            ->where('r.owner_id', $ownerId)->orderByDesc('r.updated_at')->limit(5)
            ->get([
                'r.id', 'r.voided_at', 'r.updated_at', 's.name as student_name', 's.code as student_code',
                'd.id as draft_id', 'd.activity_name_snapshot as draft_activity',
                'f.id as final_id', 'f.activity_name_snapshot as final_activity', 'f.final_score', 'f.passed',
            ])->map(fn ($row) => [
                'id' => $row->id,
                'student_name' => $row->student_name,
                'student_code' => $row->student_code,
                'activity' => $row->draft_activity ?? $row->final_activity,
                'status' => $row->voided_at ? 'void' : ($row->draft_id ? 'draft' : ($row->final_id ? 'final' : 'cancelled')),
                'assessment_id' => $row->draft_id ?? $row->final_id,
                'score' => $row->final_score !== null ? bcadd((string) $row->final_score, '0.005', 2) : null,
                'passed' => $row->passed,
                'updated_at' => $row->updated_at,
            ]);

        return Inertia::render('Dashboard', [
            'summary' => [
                'students' => $students,
                'active_drafts' => $activeDrafts,
                'final_assessments' => $finalAssessments,
                'published_programs' => $publishedPrograms,
            ],
            'setup' => [
                'students_ready' => $students > 0,
                'activities_ready' => $activities > 0,
                'rubrics_ready' => $publishedRubrics > 0,
                'reference_ready' => $referenceReady,
            ],
            'recent_sessions' => $recent,
        ]);
    }
}
