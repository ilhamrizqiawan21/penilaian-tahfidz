<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class RubricVersions
{
    public function rules(): array
    {
        $decimal = ['required', 'regex:/^\d{1,8}(?:\.\d{1,4})?$/'];

        return [
            'name' => ['required', 'string', 'max:160'],
            'pass_threshold' => $decimal,
            'criteria' => ['present', 'array', 'max:30'],
            'criteria.*.name' => ['required', 'string', 'max:160'],
            'criteria.*.description' => ['nullable', 'string', 'max:4000'],
            'criteria.*.method' => ['required', 'in:direct,deduction'],
            'criteria.*.min_score' => $decimal,
            'criteria.*.max_score' => $decimal,
            'criteria.*.weight' => $decimal,
            'criteria.*.min_pass_normalized' => ['nullable', 'regex:/^\d+(?:\.\d{1,4})?$/'],
            'criteria.*.rules' => ['present', 'array', 'max:100'],
            'criteria.*.rules.*.name' => ['required', 'string', 'max:160'],
            'criteria.*.rules.*.severity_label' => ['nullable', 'string', 'max:80'],
            'criteria.*.rules.*.deduction_points' => $decimal,
            'bands' => ['present', 'array', 'max:30'],
            'bands.*.label' => ['required', 'string', 'max:160'],
            'bands.*.lower_bound' => $decimal,
            'bands.*.upper_bound' => $decimal,
        ];
    }

    public function save(string $versionId, array $data): void
    {
        foreach ($data['criteria'] as $index => $criterion) {
            $criterionId = (string) Str::ulid();
            DB::table('criteria')->insert([
                'id' => $criterionId, 'rubric_version_id' => $versionId, 'name' => $criterion['name'],
                'description' => $criterion['description'] ?? null, 'method' => $criterion['method'],
                'min_score' => $criterion['min_score'], 'max_score' => $criterion['max_score'],
                'weight' => $criterion['weight'], 'min_pass_normalized' => $criterion['min_pass_normalized'] ?? null,
                'sort_order' => $index + 1,
            ]);
            foreach ($criterion['rules'] as $rule) {
                DB::table('mistake_rules')->insert([
                    'id' => (string) Str::ulid(), 'criterion_id' => $criterionId, 'name' => $rule['name'],
                    'severity_label' => $rule['severity_label'] ?? null, 'deduction_points' => $rule['deduction_points'],
                ]);
            }
        }
        foreach ($data['bands'] as $index => $band) {
            DB::table('grade_bands')->insert([
                'id' => (string) Str::ulid(), 'rubric_version_id' => $versionId, 'label' => $band['label'],
                'lower_bound' => $band['lower_bound'], 'upper_bound' => $band['upper_bound'], 'sort_order' => $index + 1,
            ]);
        }
    }

    public function clear(string $versionId): void
    {
        $criterionIds = DB::table('criteria')->where('rubric_version_id', $versionId)->pluck('id');
        DB::table('mistake_rules')->whereIn('criterion_id', $criterionIds)->delete();
        DB::table('criteria')->where('rubric_version_id', $versionId)->delete();
        DB::table('grade_bands')->where('rubric_version_id', $versionId)->delete();
    }

    public function hydrate(object $version): array
    {
        $criteria = DB::table('criteria')->where('rubric_version_id', $version->id)->orderBy('sort_order')->get()->map(function ($criterion) {
            $record = (array) $criterion;
            $record['rules'] = DB::table('mistake_rules')->where('criterion_id', $criterion->id)->orderBy('id')->get()->map(fn ($rule) => (array) $rule)->all();

            return $record;
        })->all();

        return [
            'id' => $version->id, 'version' => $version->version, 'name' => $version->name_snapshot,
            'status' => $version->status, 'pass_threshold' => $version->pass_threshold,
            'criteria' => $criteria,
            'bands' => DB::table('grade_bands')->where('rubric_version_id', $version->id)->orderBy('sort_order')->get()->map(fn ($band) => (array) $band)->all(),
        ];
    }

    public function hydrateMany($versions): array
    {
        $criteria = DB::table('criteria')->whereIn('rubric_version_id', $versions->pluck('id'))->orderBy('sort_order')->get();
        $rules = DB::table('mistake_rules')->whereIn('criterion_id', $criteria->pluck('id'))->orderBy('id')->get()->groupBy('criterion_id');
        $criteriaByVersion = $criteria->map(function ($criterion) use ($rules) {
            $record = (array) $criterion;
            $record['rules'] = ($rules[$criterion->id] ?? collect())->map(fn ($rule) => (array) $rule)->all();

            return $record;
        })->groupBy('rubric_version_id');
        $bands = DB::table('grade_bands')->whereIn('rubric_version_id', $versions->pluck('id'))->orderBy('sort_order')->get()->groupBy('rubric_version_id');

        return $versions->mapWithKeys(fn ($version) => [$version->id => [
            'id' => $version->id, 'version' => $version->version, 'name' => $version->name_snapshot,
            'status' => $version->status, 'pass_threshold' => $version->pass_threshold,
            'criteria' => $criteriaByVersion->get($version->id, collect())->all(),
            'bands' => $bands->get($version->id, collect())->map(fn ($band) => (array) $band)->all(),
        ]])->all();
    }

    public function validateForPublish(array $rubric): void
    {
        if (count($rubric['criteria']) === 0) {
            throw ValidationException::withMessages(['criteria' => 'Tambahkan minimal satu kriteria.']);
        }
        if (bccomp((string) $rubric['pass_threshold'], '0', 4) < 0 || bccomp((string) $rubric['pass_threshold'], '100', 4) > 0) {
            throw ValidationException::withMessages(['pass_threshold' => 'Ambang akhir harus 0–100.']);
        }
        $weight = '0';
        foreach ($rubric['criteria'] as $index => $criterion) {
            if (bccomp((string) $criterion['max_score'], (string) $criterion['min_score'], 4) <= 0) {
                throw ValidationException::withMessages(["criteria.$index.max_score" => 'Skala maksimum harus lebih besar dari minimum.']);
            }
            if (bccomp((string) $criterion['weight'], '0', 4) <= 0) {
                throw ValidationException::withMessages(["criteria.$index.weight" => 'Bobot harus positif.']);
            }
            $weight = bcadd($weight, (string) $criterion['weight'], 4);
            $threshold = $criterion['min_pass_normalized'];
            if ($threshold !== null && (bccomp((string) $threshold, '0', 4) < 0 || bccomp((string) $threshold, '100', 4) > 0)) {
                throw ValidationException::withMessages(["criteria.$index.min_pass_normalized" => 'Ambang kriteria harus 0–100.']);
            }
            if ($criterion['method'] === 'direct' && count($criterion['rules']) > 0) {
                throw ValidationException::withMessages(["criteria.$index.rules" => 'Aturan kesalahan hanya untuk kriteria pengurangan.']);
            }
            foreach ($criterion['rules'] as $ruleIndex => $rule) {
                if (bccomp((string) $rule['deduction_points'], '0', 4) <= 0) {
                    throw ValidationException::withMessages(["criteria.$index.rules.$ruleIndex.deduction_points" => 'Potongan harus positif.']);
                }
            }
        }
        if (bccomp($weight, '100', 4) !== 0) {
            throw ValidationException::withMessages(['criteria' => 'Jumlah bobot kriteria harus tepat 100%.']);
        }

        if (count($rubric['bands']) > 0) {
            $expected = '0';
            foreach ($rubric['bands'] as $index => $band) {
                if (bccomp((string) $band['lower_bound'], $expected, 4) !== 0 || bccomp((string) $band['upper_bound'], (string) $band['lower_bound'], 4) <= 0 || bccomp((string) $band['upper_bound'], '100', 4) > 0) {
                    throw ValidationException::withMessages(['bands' => 'Predikat harus berurutan tanpa celah atau tumpang tindih dari 0 sampai 100.']);
                }
                $expected = (string) $band['upper_bound'];
            }
            if (bccomp($expected, '100', 4) !== 0) {
                throw ValidationException::withMessages(['bands' => 'Predikat harus mencakup sampai 100.']);
            }
        }
    }
}
