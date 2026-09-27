<?php

namespace App\Services;

use Illuminate\Validation\ValidationException;

class ScoreCalculator
{
    private const SCALE = 12;

    public function calculate(array $rubric, array $input, bool $final): array
    {
        $direct = $input['direct'] ?? [];
        $overrides = $input['overrides'] ?? [];
        $events = $this->events($input['events'] ?? [], $rubric['criteria']);
        $items = [];
        $total = '0';
        $complete = true;
        $criteriaPassed = true;

        foreach ($rubric['criteria'] as $criterion) {
            $id = (string) $criterion['id'];
            $minimum = (string) $criterion['min_score'];
            $maximum = (string) $criterion['max_score'];
            $deductions = $events[$id] ?? [];
            if ($criterion['method'] === 'direct') {
                $value = $direct[$id] ?? null;
                if ($value === null || $value === '') {
                    if ($final) {
                        throw ValidationException::withMessages(["direct.$id" => 'Nilai langsung wajib diisi sebelum finalisasi.']);
                    }
                    $complete = false;
                    $items[] = ['id' => $id, 'name' => $criterion['name'], 'method' => 'direct', 'computed_raw' => null, 'effective_raw' => null, 'normalized' => null, 'weighted' => null, 'deductions' => []];

                    continue;
                }
                $this->inRange($value, $minimum, $maximum, "direct.$id");
                $raw = (string) $value;
            } else {
                $sum = '0';
                foreach ($deductions as $deduction) {
                    $sum = bcadd($sum, $deduction['points'], self::SCALE);
                }
                $raw = bcsub($maximum, $sum, self::SCALE);
                if (bccomp($raw, $minimum, self::SCALE) < 0) {
                    $raw = $minimum;
                }
            }

            $effective = $raw;
            if (isset($overrides[$id])) {
                $override = $overrides[$id];
                if (trim((string) ($override['reason'] ?? '')) === '') {
                    throw ValidationException::withMessages(["overrides.$id.reason" => 'Alasan override wajib diisi.']);
                }
                $this->inRange($override['raw'] ?? null, $minimum, $maximum, "overrides.$id.raw");
                $effective = (string) $override['raw'];
            }

            $normalized = bcdiv(bcmul(bcsub($effective, $minimum, self::SCALE), '100', self::SCALE), bcsub($maximum, $minimum, self::SCALE), self::SCALE);
            $weighted = bcdiv(bcmul($normalized, (string) $criterion['weight'], self::SCALE), '100', self::SCALE);
            $total = bcadd($total, $weighted, self::SCALE);
            if ($criterion['min_pass_normalized'] !== null && bccomp($normalized, (string) $criterion['min_pass_normalized'], self::SCALE) < 0) {
                $criteriaPassed = false;
            }
            $items[] = [
                'id' => $id, 'name' => $criterion['name'], 'method' => $criterion['method'],
                'computed_raw' => $this->fixed($raw), 'effective_raw' => $this->fixed($effective),
                'normalized' => $this->fixed($normalized), 'weighted' => $this->fixed($weighted),
                'deductions' => $deductions,
            ];
        }

        if (! $complete) {
            return ['complete' => false, 'unrounded_score' => null, 'display_score' => null, 'passed' => null, 'grade' => null, 'criteria' => $items];
        }

        $grade = null;
        foreach ($rubric['bands'] ?? [] as $index => $band) {
            $last = $index === count($rubric['bands']) - 1;
            if (bccomp($total, (string) $band['lower_bound'], self::SCALE) >= 0 && (bccomp($total, (string) $band['upper_bound'], self::SCALE) < 0 || ($last && bccomp($total, (string) $band['upper_bound'], self::SCALE) === 0))) {
                $grade = $band['label'];
                break;
            }
        }

        return [
            'complete' => true,
            'unrounded_score' => $this->fixed($total),
            'display_score' => bcadd($total, '0.005', 2),
            'passed' => $criteriaPassed && bccomp($total, (string) $rubric['pass_threshold'], self::SCALE) >= 0,
            'grade' => $grade,
            'criteria' => $items,
        ];
    }

    private function events(array $events, array $criteria): array
    {
        $rules = [];
        foreach ($criteria as $criterion) {
            foreach ($criterion['rules'] ?? [] as $rule) {
                $rules[(string) $rule['id']] = ['criterion_id' => (string) $criterion['id'], 'name' => $rule['name'], 'points' => (string) $rule['deduction_points']];
            }
        }
        $seen = [];
        $result = [];
        foreach ($events as $event) {
            $id = (string) ($event['id'] ?? '');
            if ($id === '') {
                throw ValidationException::withMessages(['events' => 'Setiap kejadian memerlukan ID stabil.']);
            }
            $normalized = $this->normalizeEvent($event);
            if (isset($seen[$id])) {
                if ($seen[$id] !== $normalized) {
                    throw ValidationException::withMessages(['events' => 'ID kejadian yang sama tidak boleh memiliki isi berbeda.']);
                }

                continue;
            }
            $seen[$id] = $normalized;
            if (($event['active'] ?? true) === false || ($event['kind'] ?? 'penalty') === 'note') {
                continue;
            }
            $criterionId = (string) ($event['criterion_id'] ?? '');
            $ruleId = (string) ($event['rule_id'] ?? '');
            if (! isset($rules[$ruleId]) || $rules[$ruleId]['criterion_id'] !== $criterionId) {
                throw ValidationException::withMessages(['events' => 'Aturan kesalahan tidak sesuai dengan kriteria.']);
            }
            $result[$criterionId][] = ['event_id' => $id, 'rule_id' => $ruleId, 'name' => $rules[$ruleId]['name'], 'points' => $rules[$ruleId]['points']];
        }

        return $result;
    }

    private function normalizeEvent(array $event): array
    {
        ksort($event);

        return $event;
    }

    private function inRange(mixed $value, string $minimum, string $maximum, string $key): void
    {
        if (! is_scalar($value) || ! preg_match('/^\d{1,8}(?:\.\d{1,4})?$/', (string) $value) || bccomp((string) $value, $minimum, self::SCALE) < 0 || bccomp((string) $value, $maximum, self::SCALE) > 0) {
            throw ValidationException::withMessages([$key => 'Nilai harus berada dalam skala kriteria.']);
        }
    }

    private function fixed(string $value): string
    {
        return bcadd($value, '0', 8);
    }
}
