<?php

namespace App\Http\Controllers;

use App\Services\ProgramTargets;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class ProgramController extends Controller
{
    public function index(Request $request): Response
    {
        $ownerId = $request->user()->id;
        $dataset = DB::table('quran_datasets')->where('validation_status', 'active')->first();
        $programs = DB::table('programs')->where('owner_id', $ownerId)->orderByDesc('created_at')->get();
        $programIds = $programs->pluck('id');
        $versions = DB::table('program_versions')->whereIn('program_id', $programIds)->orderByDesc('version')->get()->groupBy('program_id');
        $ranges = DB::table('program_ranges as r')
            ->join('ayahs as a', 'a.id', '=', 'r.start_ayah_id')->join('surahs as s', 's.id', '=', 'a.surah_id')
            ->join('ayahs as e', 'e.id', '=', 'r.end_ayah_id')->join('surahs as t', 't.id', '=', 'e.surah_id')
            ->whereIn('r.program_version_id', $versions->flatten()->pluck('id'))->orderBy('r.sort_order')
            ->get(['r.program_version_id', 's.number as startSurah', 'a.number as startAyah', 't.number as endSurah', 'e.number as endAyah'])->groupBy('program_version_id');
        $enrollments = DB::table('enrollments as e')->join('students as s', 's.id', '=', 'e.student_id')
            ->whereIn('e.program_id', $programIds)->whereNull('e.ended_at')
            ->get(['e.id', 'e.program_id', 'e.student_id', 'e.program_version_id', 's.name as student_name'])->groupBy('program_id');
        foreach ($programs as $program) {
            $program->versions = $versions->get($program->id, collect());
            foreach ($program->versions as $version) {
                $version->ranges = $ranges->get($version->id, collect());
            }
            $program->enrollments = $enrollments->get($program->id, collect());
        }

        return Inertia::render('Programs/Index', [
            'reference' => $dataset ? [
                'name' => $dataset->source_name,
                'surahs' => DB::table('surahs')->where('dataset_id', $dataset->id)->orderBy('number')->get(['number', 'name_local', 'ayah_count']),
                'juz' => DB::table('juz_ranges as j')->join('ayahs as a', 'a.id', '=', 'j.start_ayah_id')
                    ->join('surahs as s', 's.id', '=', 'a.surah_id')->join('ayahs as e', 'e.id', '=', 'j.end_ayah_id')
                    ->join('surahs as t', 't.id', '=', 'e.surah_id')->where('j.dataset_id', $dataset->id)
                    ->orderBy('j.juz_number')->get(['j.juz_number', 's.number as startSurah', 'a.number as startAyah', 't.number as endSurah', 'e.number as endAyah']),
            ] : null,
            'programs' => $programs,
            'students' => DB::table('students')->where('owner_id', $ownerId)->whereNull('archived_at')->orderBy('name')->get(['id', 'code', 'name']),
        ]);
    }

    public function show(Request $request, string $program): RedirectResponse
    {
        $this->program($request, $program, false);

        return to_route('programs.index');
    }

    public function archive(Request $request, string $program): RedirectResponse
    {
        $record = $this->program($request, $program, false);
        DB::table('programs')->where('id', $record->id)->update(['archived_at' => now(), 'updated_at' => now()]);

        return to_route('programs.index');
    }

    public function preview(Request $request, ProgramTargets $targets): JsonResponse
    {
        $data = $request->validate(array_filter($this->versionRules(), fn ($key) => $key === 'ranges' || str_starts_with($key, 'ranges.'), ARRAY_FILTER_USE_KEY));
        $resolved = $targets->resolve($targets->activeDataset()->id, $data['ranges']);

        return response()->json(['unique_ayahs' => $targets->uniqueCount($resolved), 'ranges' => array_map(fn ($range) => ['count' => $range['count']], $resolved)]);
    }

    public function store(Request $request, ProgramTargets $targets): RedirectResponse
    {
        $data = $request->validate($this->versionRules());
        $dataset = $targets->activeDataset();
        $ranges = $targets->resolve($dataset->id, $data['ranges']);
        DB::transaction(function () use ($request, $data, $dataset, $ranges) {
            $programId = (string) Str::ulid();
            $versionId = (string) Str::ulid();
            DB::table('programs')->insert(['id' => $programId, 'owner_id' => $request->user()->id, 'name' => $data['name'], 'created_at' => now(), 'updated_at' => now()]);
            DB::table('program_versions')->insert($this->versionRow($versionId, $programId, 1, $dataset->id, $data));
            $this->saveRanges($versionId, $ranges);
        });

        return to_route('programs.index');
    }

    public function update(Request $request, string $program, string $version, ProgramTargets $targets): RedirectResponse
    {
        $record = $this->program($request, $program);
        $current = $this->version($record->id, $version);
        abort_unless($current->status === 'draft', 409);
        $data = $request->validate($this->versionRules());
        $ranges = $targets->resolve($current->dataset_id, $data['ranges']);
        DB::transaction(function () use ($record, $version, $data, $ranges) {
            DB::table('programs')->where('id', $record->id)->lockForUpdate()->first();
            $current = $this->version($record->id, $version);
            abort_unless($current->status === 'draft', 409);
            DB::table('programs')->where('id', $record->id)->update(['name' => $data['name'], 'updated_at' => now()]);
            DB::table('program_versions')->where('id', $current->id)->update(['name_snapshot' => $data['name'], 'description' => $data['description'] ?? null, 'start_date' => $data['start_date'] ?? null, 'target_date' => $data['target_date'] ?? null, 'updated_at' => now()]);
            DB::table('program_ranges')->where('program_version_id', $current->id)->delete();
            $this->saveRanges($current->id, $ranges);
        });

        return to_route('programs.index');
    }

    public function clone(Request $request, string $program): RedirectResponse
    {
        $record = $this->program($request, $program);
        DB::transaction(function () use ($record) {
            DB::table('programs')->where('id', $record->id)->lockForUpdate()->first();
            abort_if(DB::table('program_versions')->where('program_id', $record->id)->where('status', 'draft')->exists(), 409);
            $current = DB::table('program_versions')->where('program_id', $record->id)->orderByDesc('version')->first();
            abort_unless($current, 409);
            $newId = (string) Str::ulid();
            DB::table('program_versions')->insert([
                ...$this->versionRow($newId, $record->id, $current->version + 1, $current->dataset_id, ['name' => $current->name_snapshot]),
                'description' => $current->description, 'start_date' => $current->start_date, 'target_date' => $current->target_date,
            ]);
            foreach (DB::table('program_ranges')->where('program_version_id', $current->id)->orderBy('sort_order')->get() as $range) {
                DB::table('program_ranges')->insert(['id' => (string) Str::ulid(), 'program_version_id' => $newId, 'start_ayah_id' => $range->start_ayah_id, 'end_ayah_id' => $range->end_ayah_id, 'sort_order' => $range->sort_order]);
            }
        });

        return to_route('programs.index');
    }

    public function publish(Request $request, string $program, string $version): RedirectResponse
    {
        $record = $this->program($request, $program);
        DB::transaction(function () use ($record, $version) {
            DB::table('programs')->where('id', $record->id)->lockForUpdate()->first();
            $current = $this->version($record->id, $version);
            abort_unless($current->status === 'draft', 409);
            if (! DB::table('program_ranges')->where('program_version_id', $current->id)->exists()) {
                throw ValidationException::withMessages(['ranges' => 'Program memerlukan rentang ayat.']);
            }
            $dataset = DB::table('quran_datasets')->where('id', $current->dataset_id)->first();
            if ($dataset?->validation_status !== 'active') {
                throw ValidationException::withMessages(['dataset' => 'Referensi mushaf belum aktif.']);
            }
            DB::table('program_versions')->where('program_id', $record->id)->where('status', 'published')->update(['status' => 'retired', 'updated_at' => now()]);
            DB::table('program_versions')->where('id', $current->id)->update(['status' => 'published', 'published_at' => now(), 'updated_at' => now()]);
        });

        return to_route('programs.index');
    }

    public function enroll(Request $request, string $program): RedirectResponse
    {
        $record = $this->program($request, $program);
        $data = $request->validate(['student_id' => ['required', 'ulid'], 'version_id' => ['required', 'ulid']]);
        DB::transaction(function () use ($request, $record, $data) {
            DB::table('programs')->where('id', $record->id)->lockForUpdate()->first();
            $student = DB::table('students')->where('id', $data['student_id'])->where('owner_id', $request->user()->id)->whereNull('archived_at')->first();
            if (! $student) {
                throw ValidationException::withMessages(['student_id' => 'Santri aktif tidak ditemukan.']);
            }
            $version = $this->version($record->id, $data['version_id']);
            if ($version->status !== 'published') {
                throw ValidationException::withMessages(['version_id' => 'Pilih versi terbit yang aktif.']);
            }
            if (DB::table('enrollments')->where('program_id', $record->id)->where('active_student_id', $student->id)->exists()) {
                throw ValidationException::withMessages(['student_id' => 'Santri sudah mengikuti program ini.']);
            }
            DB::table('enrollments')->insert(['id' => (string) Str::ulid(), 'student_id' => $student->id, 'program_id' => $record->id, 'program_version_id' => $version->id, 'active_student_id' => $student->id, 'started_at' => now()]);
        });

        return to_route('programs.index');
    }

    public function transfer(Request $request, string $program, string $enrollment): RedirectResponse
    {
        $record = $this->program($request, $program);
        $data = $request->validate(['version_id' => ['required', 'ulid']]);
        DB::transaction(function () use ($record, $enrollment, $data) {
            DB::table('programs')->where('id', $record->id)->lockForUpdate()->first();
            $old = DB::table('enrollments')->where('id', $enrollment)->where('program_id', $record->id)->whereNull('ended_at')->first();
            abort_unless($old, 404);
            $newVersion = $this->version($record->id, $data['version_id']);
            if ($newVersion->status !== 'published' || $newVersion->id === $old->program_version_id) {
                throw ValidationException::withMessages(['version_id' => 'Pilih versi terbit yang berbeda.']);
            }
            DB::table('enrollments')->where('id', $old->id)->update(['ended_at' => now(), 'active_student_id' => null]);
            DB::table('enrollments')->insert(['id' => (string) Str::ulid(), 'student_id' => $old->student_id, 'program_id' => $record->id, 'program_version_id' => $newVersion->id, 'previous_enrollment_id' => $old->id, 'active_student_id' => $old->student_id, 'started_at' => now()]);
        });

        return to_route('programs.index');
    }

    private function program(Request $request, string $id, bool $active = true): object
    {
        $query = DB::table('programs')->where('owner_id', $request->user()->id)->where('id', $id);
        if ($active) {
            $query->whereNull('archived_at');
        }

        return $query->first() ?? abort(404);
    }

    private function version(string $programId, string $versionId): object
    {
        return DB::table('program_versions')->where('program_id', $programId)->where('id', $versionId)->first() ?? abort(404);
    }

    private function rangeRules(): array
    {
        return ['required', 'array', 'min:1', 'max:100'];
    }

    private function versionRules(): array
    {
        return [
            'name' => ['required', 'string', 'max:160'],
            'description' => ['nullable', 'string', 'max:4000'],
            'start_date' => ['nullable', 'date'],
            'target_date' => ['nullable', 'date', 'after_or_equal:start_date'],
            'ranges' => $this->rangeRules(),
            'ranges.*.startSurah' => ['required', 'integer', 'min:1', 'max:114'],
            'ranges.*.startAyah' => ['required', 'integer', 'min:1'],
            'ranges.*.endSurah' => ['required', 'integer', 'min:1', 'max:114'],
            'ranges.*.endAyah' => ['required', 'integer', 'min:1'],
        ];
    }

    private function versionRow(string $id, string $programId, int $number, string $datasetId, array $data): array
    {
        return ['id' => $id, 'program_id' => $programId, 'version' => $number, 'dataset_id' => $datasetId, 'name_snapshot' => $data['name'], 'description' => $data['description'] ?? null, 'start_date' => $data['start_date'] ?? null, 'target_date' => $data['target_date'] ?? null, 'status' => 'draft', 'created_at' => now(), 'updated_at' => now()];
    }

    private function saveRanges(string $versionId, array $ranges): void
    {
        foreach ($ranges as $index => $range) {
            DB::table('program_ranges')->insert(['id' => (string) Str::ulid(), 'program_version_id' => $versionId, 'start_ayah_id' => $range['start_ayah_id'], 'end_ayah_id' => $range['end_ayah_id'], 'sort_order' => $index + 1]);
        }
    }
}
