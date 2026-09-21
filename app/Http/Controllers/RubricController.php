<?php

namespace App\Http\Controllers;

use App\Services\RubricVersions;
use App\Services\ScoreCalculator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class RubricController extends Controller
{
    public function index(Request $request, RubricVersions $versions): Response
    {
        $rubrics = DB::table('rubrics')->where('owner_id', $request->user()->id)->orderByDesc('created_at')->get();
        $rows = DB::table('rubric_versions')->whereIn('rubric_id', $rubrics->pluck('id'))->orderByDesc('version')->get()->groupBy('rubric_id');
        $hydrated = $versions->hydrateMany($rows->flatten());
        foreach ($rubrics as $rubric) {
            $rubric->versions = ($rows[$rubric->id] ?? collect())->map(fn ($version) => $hydrated[$version->id]);
        }

        return Inertia::render('Rubrics/Index', ['rubrics' => $rubrics]);
    }

    public function store(Request $request, RubricVersions $versions): RedirectResponse
    {
        $data = $request->validate($versions->rules());
        DB::transaction(function () use ($request, $data, $versions) {
            $rubricId = (string) Str::ulid();
            $versionId = (string) Str::ulid();
            DB::table('rubrics')->insert(['id' => $rubricId, 'owner_id' => $request->user()->id, 'name' => $data['name'], 'created_at' => now(), 'updated_at' => now()]);
            DB::table('rubric_versions')->insert($this->versionRow($versionId, $rubricId, 1, $data));
            $versions->save($versionId, $data);
        });

        return to_route('rubrics.index');
    }

    public function update(Request $request, string $rubric, string $version, RubricVersions $versions): RedirectResponse
    {
        $this->rubric($request, $rubric);
        $data = $request->validate($versions->rules());
        DB::transaction(function () use ($rubric, $version, $data, $versions) {
            DB::table('rubrics')->where('id', $rubric)->lockForUpdate()->first();
            $current = $this->version($rubric, $version);
            abort_unless($current->status === 'draft', 409);
            DB::table('rubrics')->where('id', $rubric)->update(['name' => $data['name'], 'updated_at' => now()]);
            DB::table('rubric_versions')->where('id', $version)->update(['name_snapshot' => $data['name'], 'pass_threshold' => $data['pass_threshold'], 'updated_at' => now()]);
            $versions->clear($version);
            $versions->save($version, $data);
        });

        return to_route('rubrics.index');
    }

    public function clone(Request $request, string $rubric, RubricVersions $versions): RedirectResponse
    {
        $this->rubric($request, $rubric);
        DB::transaction(function () use ($rubric, $versions) {
            DB::table('rubrics')->where('id', $rubric)->lockForUpdate()->first();
            abort_if(DB::table('rubric_versions')->where('rubric_id', $rubric)->where('status', 'draft')->exists(), 409);
            $current = DB::table('rubric_versions')->where('rubric_id', $rubric)->orderByDesc('version')->first();
            abort_unless($current, 409);
            $copy = $versions->hydrate($current);
            $newId = (string) Str::ulid();
            DB::table('rubric_versions')->insert($this->versionRow($newId, $rubric, $current->version + 1, ['name' => $current->name_snapshot, 'pass_threshold' => $current->pass_threshold]));
            $versions->save($newId, $copy);
        });

        return to_route('rubrics.index');
    }

    public function publish(Request $request, string $rubric, string $version, RubricVersions $versions): RedirectResponse
    {
        $this->rubric($request, $rubric);
        DB::transaction(function () use ($rubric, $version, $versions) {
            DB::table('rubrics')->where('id', $rubric)->lockForUpdate()->first();
            $current = $this->version($rubric, $version);
            abort_unless($current->status === 'draft', 409);
            $versions->validateForPublish($versions->hydrate($current));
            DB::table('rubric_versions')->where('rubric_id', $rubric)->where('status', 'published')->update(['status' => 'retired', 'updated_at' => now()]);
            DB::table('rubric_versions')->where('id', $version)->update(['status' => 'published', 'published_at' => now(), 'updated_at' => now()]);
        });

        return to_route('rubrics.index');
    }

    public function preview(Request $request, string $rubric, string $version, RubricVersions $versions, ScoreCalculator $calculator): JsonResponse
    {
        $this->rubric($request, $rubric, false);
        $current = $this->version($rubric, $version);
        abort_unless(in_array($current->status, ['published', 'retired'], true), 409);
        $data = $request->validate([
            'final' => ['sometimes', 'boolean'],
            'direct' => ['sometimes', 'array'], 'direct.*' => ['nullable', 'regex:/^\d+(?:\.\d{1,4})?$/'],
            'overrides' => ['sometimes', 'array'], 'overrides.*.raw' => ['required', 'regex:/^\d+(?:\.\d{1,4})?$/'], 'overrides.*.reason' => ['required', 'string', 'max:2000'],
            'events' => ['sometimes', 'array', 'max:500'], 'events.*.id' => ['required', 'string', 'max:64'],
            'events.*.criterion_id' => ['required_unless:events.*.kind,note', 'string'],
            'events.*.rule_id' => ['required_unless:events.*.kind,note', 'string'],
            'events.*.kind' => ['sometimes', 'in:note,penalty'], 'events.*.active' => ['sometimes', 'boolean'],
            'events.*.note' => ['nullable', 'string', 'max:4000'],
        ]);

        return response()->json($calculator->calculate($versions->hydrate($current), $data, (bool) ($data['final'] ?? false)));
    }

    public function archive(Request $request, string $rubric): RedirectResponse
    {
        $record = $this->rubric($request, $rubric, false);
        DB::table('rubrics')->where('id', $record->id)->update(['archived_at' => now(), 'updated_at' => now()]);

        return to_route('rubrics.index');
    }

    private function rubric(Request $request, string $id, bool $active = true): object
    {
        $query = DB::table('rubrics')->where('owner_id', $request->user()->id)->where('id', $id);
        if ($active) {
            $query->whereNull('archived_at');
        }

        return $query->first() ?? abort(404);
    }

    private function version(string $rubricId, string $versionId): object
    {
        return DB::table('rubric_versions')->where('rubric_id', $rubricId)->where('id', $versionId)->first() ?? abort(404);
    }

    private function versionRow(string $id, string $rubricId, int $number, array $data): array
    {
        return ['id' => $id, 'rubric_id' => $rubricId, 'version' => $number, 'name_snapshot' => $data['name'], 'status' => 'draft', 'pass_threshold' => $data['pass_threshold'], 'calculation_version' => 1, 'created_at' => now(), 'updated_at' => now()];
    }
}
