<?php

namespace App\Services;

use App\Support\Numbers;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class AssessmentDrafts
{
    public function __construct(private ProgramTargets $targets, private RubricVersions $rubrics, private ScoreCalculator $calculator) {}

    public function createRules(): array
    {
        return [
            'student_id' => ['required', 'ulid'], 'activity_type_id' => ['required', 'ulid'],
            'rubric_version_id' => ['required', 'ulid'], 'edition_id' => ['required', 'ulid'],
            'mutation_id' => ['required', 'string', 'max:64'],
            'enrollment_id' => ['nullable', 'ulid'],
            ...$this->rangeRules('planned_ranges', true),
        ];
    }

    public function saveRules(): array
    {
        return [
            'lock_version' => ['required', 'integer', 'min:0'], 'mutation_id' => ['required', 'string', 'max:64'],
            'direct' => ['present', 'array', 'max:30'], 'direct.*' => ['nullable', 'regex:/^\d{1,8}(?:\.\d{1,4})?$/'],
            'overrides' => ['sometimes', 'array', 'max:30'], 'overrides.*.raw' => ['required', 'regex:/^\d{1,8}(?:\.\d{1,4})?$/'], 'overrides.*.reason' => ['required', 'string', 'max:2000'],
            'events' => ['present', 'array', 'max:500'], 'events.*.id' => ['required', 'string', 'max:64'],
            'events.*.kind' => ['required', 'in:note,penalty'], 'events.*.ayah_id' => ['required', 'ulid'],
            'events.*.edition_word_id' => ['nullable', 'ulid'], 'events.*.rule_id' => ['nullable', 'ulid'],
            'events.*.note' => ['nullable', 'string', 'max:4000'], 'events.*.active' => ['required', 'boolean'],
            'last_ayah_id' => ['nullable', 'ulid'], 'notes' => ['nullable', 'string', 'max:4000'],
            ...$this->rangeRules('actual_ranges', false),
        ];
    }

    public function create(int $ownerId, array $data): array
    {
        $hash = hash('sha256', json_encode($data, JSON_THROW_ON_ERROR));
        if ($receipt = $this->createReceipt($ownerId, $data['mutation_id'], $hash)) {
            return $receipt;
        }
        $student = DB::table('students')->where('id', $data['student_id'])->where('owner_id', $ownerId)->whereNull('archived_at')->first();
        if (! $student) {
            throw ValidationException::withMessages(['student_id' => 'Santri aktif tidak ditemukan.']);
        }
        $activity = DB::table('activity_types')->where('id', $data['activity_type_id'])->where('owner_id', $ownerId)->whereNull('archived_at')->first();
        if (! $activity) {
            throw ValidationException::withMessages(['activity_type_id' => 'Kegiatan aktif tidak ditemukan.']);
        }
        $rubric = DB::table('rubric_versions as v')->join('rubrics as r', 'r.id', '=', 'v.rubric_id')
            ->where('v.id', $data['rubric_version_id'])->where('r.owner_id', $ownerId)->where('v.status', 'published')->whereNull('r.archived_at')->first(['v.*']);
        if (! $rubric) {
            throw ValidationException::withMessages(['rubric_version_id' => 'Pilih versi rubrik terbit.']);
        }
        $edition = DB::table('mushaf_editions as e')->join('quran_datasets as d', 'd.id', '=', 'e.dataset_id')
            ->where('e.id', $data['edition_id'])->where('e.status', 'active')->where('d.validation_status', 'active')->first(['e.*']);
        if (! $edition) {
            throw ValidationException::withMessages(['edition_id' => 'Edisi mushaf tervalidasi belum aktif.']);
        }
        $planned = $this->targets->resolve($edition->dataset_id, $data['planned_ranges']);
        $enrollment = null;
        if (! empty($data['enrollment_id'])) {
            $enrollment = DB::table('enrollments as e')->join('program_versions as v', 'v.id', '=', 'e.program_version_id')
                ->join('programs as p', 'p.id', '=', 'e.program_id')->where('e.id', $data['enrollment_id'])
                ->where('e.student_id', $student->id)->where('p.owner_id', $ownerId)->where('v.dataset_id', $edition->dataset_id)
                ->whereNull('e.ended_at')->whereNull('p.archived_at')->first(['e.id', 'v.id as version_id']);
            if (! $enrollment) {
                throw ValidationException::withMessages(['enrollment_id' => 'Enrollment tidak cocok dengan santri, pemilik, atau edisi.']);
            }
            $target = $this->storedRanges('program_ranges', 'program_version_id', $enrollment->version_id);
            foreach ($planned as $range) {
                if (! $this->covered($range, $target)) {
                    throw ValidationException::withMessages(['planned_ranges' => 'Materi harus berada dalam target versi program.']);
                }
            }
        }

        return DB::transaction(function () use ($ownerId, $student, $activity, $rubric, $edition, $planned, $enrollment, $data, $hash) {
            DB::table('users')->where('id', $ownerId)->lockForUpdate()->first();
            if ($receipt = $this->createReceipt($ownerId, $data['mutation_id'], $hash)) {
                return $receipt;
            }
            $recordId = (string) Str::ulid();
            $assessmentId = (string) Str::ulid();
            DB::table('assessment_records')->insert(['id' => $recordId, 'owner_id' => $ownerId, 'student_id' => $student->id, 'created_at' => now(), 'updated_at' => now()]);
            DB::table('assessments')->insert([
                'id' => $assessmentId, 'record_id' => $recordId, 'active_draft_record_id' => $recordId,
                'revision_number' => 1, 'enrollment_id' => $enrollment?->id, 'activity_type_id' => $activity->id,
                'activity_name_snapshot' => $activity->name, 'counts_toward_progress_snapshot' => $activity->counts_toward_progress,
                'rubric_version_id' => $rubric->id, 'edition_id' => $edition->id,
                'status' => 'draft', 'lock_version' => 0, 'created_at' => now(), 'updated_at' => now(),
            ]);
            $this->insertRanges($assessmentId, 'planned', $planned);
            foreach (DB::table('criteria')->where('rubric_version_id', $rubric->id)->get() as $criterion) {
                DB::table('criterion_scores')->insert(['id' => (string) Str::ulid(), 'assessment_id' => $assessmentId, 'criterion_id' => $criterion->id]);
            }
            $this->audit($ownerId, $recordId, 'created');

            $response = ['id' => $assessmentId, 'record_id' => $recordId, 'lock_version' => 0];
            $this->storeReceipt($ownerId, $data['mutation_id'], $assessmentId, 'create', $hash, $response);

            return $response;
        });
    }

    private function createReceipt(int $ownerId, string $mutationId, string $hash): ?array
    {
        $receipt = DB::table('mutation_receipts')->where('owner_id', $ownerId)->where('mutation_id', $mutationId)->first();
        if (! $receipt) {
            return null;
        }
        abort_unless($receipt->resource_type === 'create' && hash_equals($receipt->request_hash, $hash), 409, 'Mutation ID dipakai untuk permintaan berbeda.');

        return json_decode($receipt->response_payload, true, 512, JSON_THROW_ON_ERROR);
    }

    public function authorize(int $ownerId, string $id): void
    {
        abort_unless(
            DB::table('assessments as a')
                ->join('assessment_records as r', 'r.id', '=', 'a.record_id')
                ->where('a.id', $id)
                ->where('r.owner_id', $ownerId)
                ->exists(),
            404
        );
    }

    public function show(int $ownerId, string $id, int $after = 0): array
    {
        $assessment = $this->assessment($ownerId, $id);
        $edition = DB::table('mushaf_editions')->where('id', $assessment->edition_id)->first();
        $ranges = DB::table('assessment_ranges')->where('assessment_id', $id)->orderBy('sort_order')->get()->groupBy('kind');
        $all = $ranges->flatten();
        $ayahs = DB::table('ayahs')->whereIn('id', $all->pluck('start_ayah_id')->merge($all->pluck('end_ayah_id')))->get()->keyBy('id');
        $surahs = DB::table('surahs')->whereIn('id', $ayahs->pluck('surah_id'))->get()->keyBy('id');
        $convert = fn ($kind) => ($ranges[$kind] ?? collect())->map(function ($range) use ($ayahs, $surahs) {
            $start = $ayahs[$range->start_ayah_id];
            $end = $ayahs[$range->end_ayah_id];

            return ['startSurah' => $surahs[$start->surah_id]->number, 'startAyah' => $start->number, 'endSurah' => $surahs[$end->surah_id]->number, 'endAyah' => $end->number];
        })->all();
        $plannedBounds = $this->storedRanges('assessment_ranges', 'assessment_id', $id, 'planned');
        $page = DB::table('ayahs as a')->join('surahs as s', 's.id', '=', 'a.surah_id')
            ->where('a.dataset_id', $edition->dataset_id)->where('a.global_order', '>', $after)
            ->where(function ($query) use ($plannedBounds) {
                foreach ($plannedBounds as $bound) {
                    $query->orWhereBetween('a.global_order', [$bound['start_order'], $bound['end_order']]);
                }
            })->orderBy('a.global_order')->limit(51)
            ->get(['a.id', 'a.number', 'a.global_order', 'a.text_uthmani', 's.number as surah_number', 's.name_local as surah_name']);
        $hasMore = $page->count() > 50;
        $page = $page->take(50);
        $words = DB::table('edition_words')->where('edition_id', $edition->id)->whereIn('ayah_id', $page->pluck('id'))->where('token_kind', 'word')->orderBy('position')->get(['id', 'ayah_id', 'position', 'text_or_glyph'])->groupBy('ayah_id');
        $page->each(fn ($ayah) => $ayah->words = $words->get($ayah->id, collect()));
        $student = DB::table('students')->where('id', $assessment->student_id)->first(['id', 'name', 'code']);

        return [
            'id' => $id, 'record_id' => $assessment->record_id, 'student_id' => $assessment->student_id,
            'student' => $student, 'activity_type_id' => $assessment->activity_type_id,
            'status' => $assessment->status, 'lock_version' => $assessment->lock_version,
            'notes' => $assessment->notes, 'last_ayah_id' => $assessment->last_ayah_id,
            'activity_name' => $assessment->activity_name_snapshot, 'edition' => $edition,
            'rubric' => $this->rubrics->hydrate(DB::table('rubric_versions')->where('id', $assessment->rubric_version_id)->first()),
            'planned_ranges' => $convert('planned'), 'actual_ranges' => $convert('actual'),
            'scores' => DB::table('criterion_scores')->where('assessment_id', $id)->get()->map(function ($score) {
                $score->direct_input = Numbers::trim($score->direct_input);
                $score->override_raw = Numbers::trim($score->override_raw);

                return $score;
            }),
            'events' => DB::table('annotations')->where('assessment_id', $id)->orderBy('created_at')->get(),
            'final_score' => $assessment->final_score, 'passed' => $assessment->passed,
            'ayahs' => $page, 'has_more_ayahs' => $hasMore,
        ];
    }

    public function save(int $ownerId, string $id, array $data): array
    {
        return DB::transaction(function () use ($ownerId, $id, $data) {
            $assessment = $this->lockedAssessment($ownerId, $id);
            $hash = $this->hash('save', $id, $data);
            if ($previous = $this->receipt($ownerId, $data['mutation_id'], $id, 'save', $hash)) {
                return $previous;
            }
            abort_unless($assessment->status === 'draft' && $assessment->lock_version === $data['lock_version'], 409);
            $edition = DB::table('mushaf_editions')->where('id', $assessment->edition_id)->first();
            $actual = $this->targets->resolve($edition->dataset_id, $data['actual_ranges']);
            $planned = $this->storedRanges('assessment_ranges', 'assessment_id', $id, 'planned');
            foreach ($actual as $range) {
                if (! $this->covered($range, $planned)) {
                    throw ValidationException::withMessages(['actual_ranges' => 'Bacaan aktual harus berada dalam materi rencana.']);
                }
            }
            if (! empty($data['last_ayah_id']) && ! DB::table('ayahs')->where('id', $data['last_ayah_id'])->where('dataset_id', $edition->dataset_id)->exists()) {
                throw ValidationException::withMessages(['last_ayah_id' => 'Ayat terakhir tidak sesuai edisi.']);
            }
            $rubric = $this->rubrics->hydrate(DB::table('rubric_versions')->where('id', $assessment->rubric_version_id)->first());
            $criterionIds = collect($rubric['criteria'])->pluck('id')->all();
            foreach (array_keys(($data['direct'] ?? []) + ($data['overrides'] ?? [])) as $criterionId) {
                if (! in_array($criterionId, $criterionIds, true)) {
                    throw ValidationException::withMessages(['direct' => 'Kriteria tidak termasuk versi rubrik sesi.']);
                }
            }
            $events = $this->validateEvents($data['events'], $rubric, $edition, $actual);
            $preview = $this->calculator->calculate($rubric, ['direct' => $data['direct'], 'overrides' => $data['overrides'] ?? [], 'events' => $events], false);

            DB::table('assessment_ranges')->where('assessment_id', $id)->where('kind', 'actual')->delete();
            $this->insertRanges($id, 'actual', $actual);
            $this->saveScores($id, $data['direct'], $data['overrides'] ?? [], $preview);
            $this->saveEvents($id, $data['events']);
            DB::table('assessments')->where('id', $id)->update(['lock_version' => $assessment->lock_version + 1, 'last_ayah_id' => $data['last_ayah_id'] ?? null, 'notes' => $data['notes'] ?? null, 'updated_at' => now()]);
            $response = ['id' => $id, 'lock_version' => $assessment->lock_version + 1, 'saved_at' => now()->toISOString(), 'preview' => $preview];
            $this->storeReceipt($ownerId, $data['mutation_id'], $id, 'save', $hash, $response);

            return $response;
        });
    }

    public function finalize(int $ownerId, string $id, array $data): array
    {
        return DB::transaction(function () use ($ownerId, $id, $data) {
            $assessment = $this->lockedAssessment($ownerId, $id);
            $hash = $this->hash('finalize', $id, $data);
            if ($previous = $this->receipt($ownerId, $data['mutation_id'], $id, 'finalize', $hash)) {
                return $previous;
            }
            abort_unless($assessment->status === 'draft' && $assessment->lock_version === $data['lock_version'], 409);
            $actual = $this->storedRanges('assessment_ranges', 'assessment_id', $id, 'actual');
            if ($actual === []) {
                throw ValidationException::withMessages(['actual_ranges' => 'Cakupan bacaan aktual wajib diisi.']);
            }
            if ($assessment->previous_assessment_id && $assessment->current_final_id !== $assessment->previous_assessment_id) {
                abort(409, 'Hasil final berubah sejak draf revisi dibuat.');
            }
            $rubric = $this->rubrics->hydrate(DB::table('rubric_versions')->where('id', $assessment->rubric_version_id)->first());
            $scores = DB::table('criterion_scores')->where('assessment_id', $id)->get();
            $direct = $scores->whereNotNull('direct_input')->mapWithKeys(fn ($score) => [$score->criterion_id => $score->direct_input])->all();
            $overrides = $scores->whereNotNull('override_raw')->mapWithKeys(fn ($score) => [$score->criterion_id => ['raw' => $score->override_raw, 'reason' => $score->override_reason]])->all();
            $events = $this->scoreEvents($id, $rubric);
            $result = $this->calculator->calculate($rubric, ['direct' => $direct, 'overrides' => $overrides, 'events' => $events], true);
            $this->saveScores($id, $direct, $overrides, $result);
            DB::table('assessments')->where('id', $id)->update([
                'status' => 'final', 'active_draft_record_id' => null, 'lock_version' => $assessment->lock_version + 1,
                'final_score' => $result['unrounded_score'], 'passed' => $result['passed'], 'grade_label_snapshot' => $result['grade'],
                'assessed_at' => now(), 'finalized_at' => now(), 'updated_at' => now(),
            ]);
            if ($assessment->current_final_id) {
                DB::table('assessments')->where('id', $assessment->current_final_id)->update(['status' => 'superseded', 'updated_at' => now()]);
            }
            DB::table('assessment_records')->where('id', $assessment->record_id)->update(['current_final_id' => $id, 'updated_at' => now()]);
            $this->audit($ownerId, $assessment->record_id, 'finalized');
            $response = ['id' => $id, 'record_id' => $assessment->record_id, 'lock_version' => $assessment->lock_version + 1, ...$result];
            $this->storeReceipt($ownerId, $data['mutation_id'], $id, 'finalize', $hash, $response);

            return $response;
        });
    }

    public function revise(int $ownerId, string $recordId, string $reason): array
    {
        return DB::transaction(function () use ($ownerId, $recordId, $reason) {
            $record = DB::table('assessment_records')->where('id', $recordId)->where('owner_id', $ownerId)->lockForUpdate()->first();
            abort_unless($record && ! $record->voided_at && $record->current_final_id, 404);
            abort_if(DB::table('assessments')->where('record_id', $recordId)->where('status', 'draft')->exists(), 409);
            $old = DB::table('assessments')->where('id', $record->current_final_id)->first();
            $number = DB::table('assessments')->where('record_id', $recordId)->max('revision_number') + 1;
            $id = (string) Str::ulid();
            DB::table('assessments')->insert([
                'id' => $id, 'record_id' => $recordId, 'active_draft_record_id' => $recordId,
                'revision_number' => $number, 'previous_assessment_id' => $old->id,
                'enrollment_id' => $old->enrollment_id, 'activity_type_id' => $old->activity_type_id,
                'activity_name_snapshot' => $old->activity_name_snapshot, 'counts_toward_progress_snapshot' => $old->counts_toward_progress_snapshot,
                'rubric_version_id' => $old->rubric_version_id, 'edition_id' => $old->edition_id,
                'status' => 'draft', 'lock_version' => 0, 'last_ayah_id' => $old->last_ayah_id,
                'notes' => $old->notes, 'revision_reason' => $reason, 'created_at' => now(), 'updated_at' => now(),
            ]);
            foreach (DB::table('assessment_ranges')->where('assessment_id', $old->id)->get() as $range) {
                DB::table('assessment_ranges')->insert(['id' => (string) Str::ulid(), 'assessment_id' => $id, 'kind' => $range->kind, 'start_ayah_id' => $range->start_ayah_id, 'end_ayah_id' => $range->end_ayah_id, 'sort_order' => $range->sort_order]);
            }
            foreach (DB::table('criterion_scores')->where('assessment_id', $old->id)->get() as $score) {
                DB::table('criterion_scores')->insert(['id' => (string) Str::ulid(), 'assessment_id' => $id, 'criterion_id' => $score->criterion_id, 'direct_input' => $score->direct_input, 'computed_raw' => $score->computed_raw, 'override_raw' => $score->override_raw, 'override_reason' => $score->override_reason, 'normalized_score' => $score->normalized_score, 'weighted_score' => $score->weighted_score]);
            }
            foreach (DB::table('annotations')->where('assessment_id', $old->id)->get() as $event) {
                DB::table('annotations')->insert(['id' => (string) Str::ulid(), 'assessment_id' => $id, 'client_event_id' => $event->client_event_id, 'ayah_id' => $event->ayah_id, 'edition_word_id' => $event->edition_word_id, 'kind' => $event->kind, 'mistake_rule_id' => $event->mistake_rule_id, 'note' => $event->note, 'retracted_at' => $event->retracted_at, 'created_at' => now(), 'updated_at' => now()]);
            }
            $this->audit($ownerId, $recordId, 'revision_started');

            return ['id' => $id, 'record_id' => $recordId, 'lock_version' => 0];
        });
    }

    public function cancel(int $ownerId, string $id): array
    {
        return DB::transaction(function () use ($ownerId, $id) {
            $assessment = $this->lockedAssessment($ownerId, $id);
            abort_unless($assessment->status === 'draft', 409);
            DB::table('assessments')->where('id', $id)->update(['status' => 'cancelled', 'active_draft_record_id' => null, 'updated_at' => now()]);
            $this->audit($ownerId, $assessment->record_id, 'draft_cancelled');

            return ['id' => $id, 'status' => 'cancelled'];
        });
    }

    public function void(int $ownerId, string $recordId, string $reason): array
    {
        return DB::transaction(function () use ($ownerId, $recordId, $reason) {
            $record = DB::table('assessment_records')->where('id', $recordId)->where('owner_id', $ownerId)->lockForUpdate()->first();
            abort_unless($record, 404);
            abort_if($record->voided_at || ! $record->current_final_id, 409);
            abort_if(DB::table('assessments')->where('record_id', $recordId)->where('status', 'draft')->exists(), 409);
            DB::table('assessments')->where('id', $record->current_final_id)->update(['status' => 'void', 'updated_at' => now()]);
            DB::table('assessment_records')->where('id', $recordId)->update(['current_final_id' => null, 'voided_at' => now(), 'void_reason' => $reason, 'updated_at' => now()]);
            $this->audit($ownerId, $recordId, 'voided');

            return ['record_id' => $recordId, 'status' => 'void'];
        });
    }

    private function rangeRules(string $field, bool $required): array
    {
        return [
            $field => [$required ? 'required' : 'present', 'array', ...($required ? ['min:1'] : []), 'max:100'],
            "$field.*.startSurah" => ['required', 'integer', 'min:1', 'max:114'],
            "$field.*.startAyah" => ['required', 'integer', 'min:1'],
            "$field.*.endSurah" => ['required', 'integer', 'min:1', 'max:114'],
            "$field.*.endAyah" => ['required', 'integer', 'min:1'],
        ];
    }

    private function assessment(int $ownerId, string $id): object
    {
        return DB::table('assessments as a')->join('assessment_records as r', 'r.id', '=', 'a.record_id')
            ->where('a.id', $id)->where('r.owner_id', $ownerId)->first(['a.*', 'r.student_id', 'r.current_final_id', 'r.voided_at']) ?? abort(404);
    }

    private function lockedAssessment(int $ownerId, string $id): object
    {
        $assessment = $this->assessment($ownerId, $id);
        DB::table('assessment_records')->where('id', $assessment->record_id)->lockForUpdate()->first();
        $current = $this->assessment($ownerId, $id);
        abort_if($current->voided_at, 409);

        return $current;
    }

    private function storedRanges(string $table, string $key, string $value, ?string $kind = null): array
    {
        $query = DB::table("$table as r")->join('ayahs as a', 'a.id', '=', 'r.start_ayah_id')->join('ayahs as e', 'e.id', '=', 'r.end_ayah_id')->where("r.$key", $value);
        if ($kind !== null) {
            $query->where('r.kind', $kind);
        }

        return $query->orderBy('r.sort_order')->get(['r.start_ayah_id', 'r.end_ayah_id', 'a.global_order as start_order', 'e.global_order as end_order'])->map(fn ($range) => (array) $range)->all();
    }

    private function covered(array $range, array $bounds): bool
    {
        $cursor = $range['start_order'];
        usort($bounds, fn ($a, $b) => $a['start_order'] <=> $b['start_order']);
        foreach ($bounds as $bound) {
            if ($bound['start_order'] > $cursor) {
                return false;
            }
            if ($bound['end_order'] >= $cursor) {
                $cursor = $bound['end_order'] + 1;
            }
            if ($cursor > $range['end_order']) {
                return true;
            }
        }

        return false;
    }

    private function insertRanges(string $assessmentId, string $kind, array $ranges): void
    {
        foreach ($ranges as $index => $range) {
            DB::table('assessment_ranges')->insert(['id' => (string) Str::ulid(), 'assessment_id' => $assessmentId, 'kind' => $kind, 'start_ayah_id' => $range['start_ayah_id'], 'end_ayah_id' => $range['end_ayah_id'], 'sort_order' => $index + 1]);
        }
    }

    private function validateEvents(array $events, array $rubric, object $edition, array $actual): array
    {
        $rules = collect($rubric['criteria'])->flatMap(fn ($criterion) => collect($criterion['rules'])->mapWithKeys(fn ($rule) => [$rule['id'] => ['criterion_id' => $criterion['id'], 'method' => $criterion['method']]]))->all();
        $seen = [];
        $scoring = [];
        foreach ($events as $index => $event) {
            if (isset($seen[$event['id']])) {
                throw ValidationException::withMessages(["events.$index.id" => 'ID kejadian duplikat.']);
            }
            $seen[$event['id']] = true;
            $ayah = DB::table('ayahs')->where('id', $event['ayah_id'])->where('dataset_id', $edition->dataset_id)->first();
            if (! $ayah || ($event['active'] && ! $this->covered(['start_order' => $ayah->global_order, 'end_order' => $ayah->global_order], $actual))) {
                throw ValidationException::withMessages(["events.$index.ayah_id" => 'Anotasi aktif harus berada dalam bacaan aktual dan edisi yang sama.']);
            }
            if (! empty($event['edition_word_id'])) {
                $word = DB::table('edition_words')->where('id', $event['edition_word_id'])->where('edition_id', $edition->id)->where('ayah_id', $ayah->id)->where('token_kind', 'word')->first();
                if (! $word) {
                    throw ValidationException::withMessages(["events.$index.edition_word_id" => 'Kata tidak cocok dengan ayat dan edisi.']);
                }
            }
            if ($event['kind'] === 'penalty') {
                if (! isset($rules[$event['rule_id'] ?? '']) || $rules[$event['rule_id']]['method'] !== 'deduction') {
                    throw ValidationException::withMessages(["events.$index.rule_id" => 'Aturan potongan tidak termasuk rubrik sesi.']);
                }
                $scoring[] = ['id' => $event['id'], 'criterion_id' => $rules[$event['rule_id']]['criterion_id'], 'rule_id' => $event['rule_id'], 'active' => $event['active']];
            } else {
                if (! empty($event['rule_id'])) {
                    throw ValidationException::withMessages(["events.$index.rule_id" => 'Catatan biasa tidak memakai aturan potongan.']);
                }
                $scoring[] = ['id' => $event['id'], 'kind' => 'note', 'active' => $event['active']];
            }
        }

        return $scoring;
    }

    private function saveScores(string $id, array $direct, array $overrides, array $result): void
    {
        foreach ($result['criteria'] as $item) {
            DB::table('criterion_scores')->where('assessment_id', $id)->where('criterion_id', $item['id'])->update([
                'direct_input' => $direct[$item['id']] ?? null,
                'computed_raw' => $item['computed_raw'], 'override_raw' => $overrides[$item['id']]['raw'] ?? null,
                'override_reason' => $overrides[$item['id']]['reason'] ?? null,
                'normalized_score' => $item['normalized'], 'weighted_score' => $item['weighted'],
            ]);
        }
    }

    private function saveEvents(string $id, array $events): void
    {
        $existing = DB::table('annotations')->where('assessment_id', $id)->get()->keyBy('client_event_id');
        $present = [];
        foreach ($events as $event) {
            $present[] = $event['id'];
            $row = ['ayah_id' => $event['ayah_id'], 'edition_word_id' => $event['edition_word_id'] ?? null, 'kind' => $event['kind'], 'mistake_rule_id' => $event['kind'] === 'penalty' ? $event['rule_id'] : null, 'note' => $event['note'] ?? null];
            if (isset($existing[$event['id']])) {
                $old = $existing[$event['id']];
                if ($old->ayah_id !== $row['ayah_id'] || $old->edition_word_id !== $row['edition_word_id'] || $old->kind !== $row['kind'] || $old->mistake_rule_id !== $row['mistake_rule_id']) {
                    abort(409, 'Identitas kejadian tidak boleh diubah.');
                }
                abort_if($old->retracted_at !== null && $event['active'], 409, 'Kejadian yang di-undo tidak dapat diaktifkan ulang.');
                DB::table('annotations')->where('id', $old->id)->update(['note' => $row['note'], 'retracted_at' => $event['active'] ? $old->retracted_at : ($old->retracted_at ?? now()), 'updated_at' => now()]);
            } else {
                DB::table('annotations')->insert(['id' => (string) Str::ulid(), 'assessment_id' => $id, 'client_event_id' => $event['id'], ...$row, 'retracted_at' => $event['active'] ? null : now(), 'created_at' => now(), 'updated_at' => now()]);
            }
        }
        DB::table('annotations')->where('assessment_id', $id)->whereNotIn('client_event_id', $present)->whereNull('retracted_at')->update(['retracted_at' => now(), 'updated_at' => now()]);
    }

    private function scoreEvents(string $id, array $rubric): array
    {
        $ruleCriteria = collect($rubric['criteria'])->flatMap(fn ($criterion) => collect($criterion['rules'])->mapWithKeys(fn ($rule) => [$rule['id'] => $criterion['id']]))->all();

        return DB::table('annotations')->where('assessment_id', $id)->get()->map(function ($event) use ($ruleCriteria) {
            return $event->kind === 'note'
                ? ['id' => $event->client_event_id, 'kind' => 'note', 'active' => $event->retracted_at === null]
                : ['id' => $event->client_event_id, 'criterion_id' => $ruleCriteria[$event->mistake_rule_id], 'rule_id' => $event->mistake_rule_id, 'active' => $event->retracted_at === null];
        })->all();
    }

    private function hash(string $action, string $id, array $data): string
    {
        return hash('sha256', json_encode([$action, $id, $data], JSON_THROW_ON_ERROR));
    }

    private function receipt(int $ownerId, string $mutationId, string $id, string $action, string $hash): ?array
    {
        $receipt = DB::table('mutation_receipts')->where('owner_id', $ownerId)->where('mutation_id', $mutationId)->first();
        if (! $receipt) {
            return null;
        }
        if ($receipt->expires_at !== null && now()->toDateTimeString() >= $receipt->expires_at) {
            DB::table('mutation_receipts')->where('id', $receipt->id)->delete();

            return null;
        }
        abort_unless($receipt->resource_id === $id && $receipt->resource_type === $action && hash_equals($receipt->request_hash, $hash), 409, 'Mutation ID dipakai untuk permintaan berbeda.');

        return json_decode($receipt->response_payload, true, 512, JSON_THROW_ON_ERROR);
    }

    private function storeReceipt(int $ownerId, string $mutationId, string $id, string $action, string $hash, array $response): void
    {
        DB::table('mutation_receipts')->insert([
            'id' => (string) Str::ulid(),
            'owner_id' => $ownerId,
            'mutation_id' => $mutationId,
            'resource_type' => $action,
            'resource_id' => $id,
            'request_hash' => $hash,
            'result_revision' => $response['lock_version'],
            'response_payload' => json_encode($response, JSON_THROW_ON_ERROR),
            'expires_at' => $action === 'save' ? now()->addHours(24) : null,
            'created_at' => now(),
        ]);
    }

    private function audit(int $ownerId, string $recordId, string $action): void
    {
        DB::table('audit_events')->insert(['id' => (string) Str::ulid(), 'owner_id' => $ownerId, 'actor_id' => $ownerId, 'entity_type' => 'assessment_record', 'entity_id' => $recordId, 'action' => $action, 'safe_metadata' => '{}', 'created_at' => now()]);
    }
}
