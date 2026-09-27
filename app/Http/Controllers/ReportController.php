<?php

namespace App\Http\Controllers;

use App\Services\AssessmentReports;
use App\Support\Numbers;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ReportController extends Controller
{
    private function filters(Request $request): array
    {
        $data = $request->validate([
            'student_id' => ['nullable', 'ulid'], 'program_id' => ['nullable', 'ulid'],
            'activity_type_id' => ['nullable', 'ulid'], 'rubric_version_id' => ['nullable', 'ulid'],
            'from' => ['nullable', 'date_format:Y-m-d'], 'to' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:from'],
        ]);

        return array_filter($data, fn ($value) => $value !== null && $value !== '');
    }

    public function index(Request $request, AssessmentReports $reports): Response
    {
        $filters = $this->filters($request);
        $ownerId = $request->user()->id;

        return Inertia::render('Reports/Index', [
            ...$reports->index($ownerId, $filters), 'filters' => $filters,
            'students' => DB::table('students')->where('owner_id', $ownerId)->orderBy('name')->get(['id', 'name', 'code']),
            'programs' => DB::table('programs')->where('owner_id', $ownerId)->orderBy('name')->get(['id', 'name']),
            'activities' => DB::table('activity_types')->where('owner_id', $ownerId)->orderBy('name')->get(['id', 'name']),
            'rubrics' => DB::table('rubric_versions as v')->join('rubrics as r', 'r.id', '=', 'v.rubric_id')->where('r.owner_id', $ownerId)->orderBy('v.name_snapshot')->get(['v.id', 'v.name_snapshot', 'v.version']),
        ]);
    }

    public function student(Request $request, string $student, AssessmentReports $reports): Response
    {
        return Inertia::render('Reports/Student', $reports->profile($request->user()->id, $student));
    }

    public function export(Request $request, AssessmentReports $reports): StreamedResponse
    {
        $filters = $this->filters($request);
        $ownerId = $request->user()->id;

        return response()->streamDownload(function () use ($reports, $ownerId, $filters) {
            $output = fopen('php://output', 'w');
            fwrite($output, "\xEF\xBB\xBF");
            fputcsv($output, ['Tanggal', 'Kode santri', 'Santri', 'Program', 'Versi program', 'Kegiatan', 'Rubrik', 'Versi rubrik', 'Nilai', 'Lulus', 'Predikat']);
            $query = $reports->selectRows($reports->finals($ownerId, $filters))->orderBy('a.id');
            foreach ($query->cursor() as $row) {
                fputcsv($output, array_map($this->safeCsv(...), [
                    $row->assessed_at, $row->student_code, $row->student_name, $row->program_name,
                    $row->program_version, $row->activity_name_snapshot, $row->rubric_name, $row->rubric_version,
                    Numbers::trim(bcadd((string) $row->final_score, '0.005', 2)), $row->passed ? 'Ya' : 'Tidak', $row->grade_label_snapshot,
                ]));
            }
            fclose($output);
        }, 'laporan-penilaian.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    private function safeCsv(mixed $value): string
    {
        $text = (string) ($value ?? '');

        return preg_match('/^[\s\x00-\x1F]*[=+\-@]/u', $text) ? "'".$text : $text;
    }
}
