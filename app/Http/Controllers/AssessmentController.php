<?php

namespace App\Http\Controllers;

use App\Services\AssessmentDrafts;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class AssessmentController extends Controller
{
    public function index(Request $request): Response
    {
        $owner = $request->user()->id;
        $edition = DB::table('mushaf_editions as e')->join('quran_datasets as d', 'd.id', '=', 'e.dataset_id')
            ->where('e.status', 'active')->where('d.validation_status', 'active')->first(['e.id', 'e.name', 'e.dataset_id']);

        return Inertia::render('Assessments/Index', [
            'edition' => $edition,
            'surahs' => $edition ? DB::table('surahs')->where('dataset_id', $edition->dataset_id)->orderBy('number')->get(['number', 'name_local', 'ayah_count']) : [],
            'students' => DB::table('students')->where('owner_id', $owner)->whereNull('archived_at')->orderBy('name')->get(['id', 'name', 'code']),
            'activities' => DB::table('activity_types')->where('owner_id', $owner)->whereNull('archived_at')->orderBy('name')->get(['id', 'name']),
            'rubrics' => DB::table('rubric_versions as v')->join('rubrics as r', 'r.id', '=', 'v.rubric_id')
                ->where('r.owner_id', $owner)->whereNull('r.archived_at')->where('v.status', 'published')->get(['v.id', 'v.name_snapshot']),
            'records' => DB::table('assessment_records as r')->join('students as s', 's.id', '=', 'r.student_id')
                ->leftJoin('assessments as a', 'a.active_draft_record_id', '=', 'r.id')
                ->where('r.owner_id', $owner)->orderByDesc('r.created_at')->limit(30)->get(['r.id', 'r.current_final_id', 'r.voided_at', 's.name as student_name', 'a.id as draft_id']),
        ]);
    }

    public function work(Request $request, string $assessment, AssessmentDrafts $drafts): Response
    {
        $drafts->show($request->user()->id, $assessment);

        return Inertia::render('Assessments/Work', ['assessmentId' => $assessment]);
    }

    public function show(Request $request, string $assessment, AssessmentDrafts $drafts): JsonResponse
    {
        return response()->json($drafts->show($request->user()->id, $assessment, max(0, (int) $request->query('after', 0))));
    }

    public function store(Request $request, AssessmentDrafts $drafts): JsonResponse
    {
        $data = $request->validate($drafts->createRules());

        return response()->json($drafts->create($request->user()->id, $data), 201);
    }

    public function save(Request $request, string $assessment, AssessmentDrafts $drafts): JsonResponse
    {
        $data = $request->validate($drafts->saveRules());

        return response()->json($drafts->save($request->user()->id, $assessment, $data));
    }

    public function finalize(Request $request, string $assessment, AssessmentDrafts $drafts): JsonResponse
    {
        $data = $request->validate(['lock_version' => ['required', 'integer', 'min:0'], 'mutation_id' => ['required', 'string', 'max:64']]);

        return response()->json($drafts->finalize($request->user()->id, $assessment, $data));
    }

    public function revise(Request $request, string $record, AssessmentDrafts $drafts): JsonResponse
    {
        $data = $request->validate(['reason' => ['required', 'string', 'max:4000']]);

        return response()->json($drafts->revise($request->user()->id, $record, $data['reason']), 201);
    }

    public function cancel(Request $request, string $assessment, AssessmentDrafts $drafts): JsonResponse
    {
        return response()->json($drafts->cancel($request->user()->id, $assessment));
    }

    public function void(Request $request, string $record, AssessmentDrafts $drafts): JsonResponse
    {
        $data = $request->validate(['reason' => ['required', 'string', 'max:4000']]);

        return response()->json($drafts->void($request->user()->id, $record, $data['reason']));
    }
}
