<?php

namespace Tests\Unit;

use App\Services\ScoreCalculator;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class ScoreCalculatorTest extends TestCase
{
    private function rubric(): array
    {
        return [
            'pass_threshold' => '80',
            'criteria' => [
                ['id' => 'fluency', 'name' => 'Kelancaran', 'method' => 'deduction', 'min_score' => '0', 'max_score' => '100', 'weight' => '50', 'min_pass_normalized' => null, 'rules' => [['id' => 'pause', 'name' => 'Terhenti', 'deduction_points' => '5']]],
                ['id' => 'tajwid', 'name' => 'Tajwid', 'method' => 'deduction', 'min_score' => '0', 'max_score' => '100', 'weight' => '30', 'min_pass_normalized' => '85', 'rules' => [['id' => 'makhraj', 'name' => 'Makhraj', 'deduction_points' => '20']]],
                ['id' => 'fashahah', 'name' => 'Fashahah', 'method' => 'direct', 'min_score' => '0', 'max_score' => '100', 'weight' => '20', 'min_pass_normalized' => null, 'rules' => []],
            ],
            'bands' => [['label' => 'Perlu latihan', 'lower_bound' => '0', 'upper_bound' => '80'], ['label' => 'Baik', 'lower_bound' => '80', 'upper_bound' => '100']],
        ];
    }

    public function test_cap04_example_and_criterion_threshold(): void
    {
        $result = (new ScoreCalculator)->calculate($this->rubric(), [
            'direct' => ['fashahah' => '85'],
            'events' => [
                ['id' => 'p1', 'criterion_id' => 'fluency', 'rule_id' => 'pause', 'active' => true],
                ['id' => 'p2', 'criterion_id' => 'fluency', 'rule_id' => 'pause', 'active' => true],
                ['id' => 'm1', 'criterion_id' => 'tajwid', 'rule_id' => 'makhraj', 'active' => true],
                ['id' => 'note', 'kind' => 'note', 'active' => true],
                ['id' => 'undone', 'criterion_id' => 'tajwid', 'rule_id' => 'makhraj', 'active' => false],
            ],
        ], true);

        $this->assertSame('86.00', $result['display_score']);
        $this->assertSame('90.00000000', $result['criteria'][0]['computed_raw']);
        $this->assertSame('80.00000000', $result['criteria'][1]['computed_raw']);
        $this->assertFalse($result['passed']);
        $this->assertSame('Baik', $result['grade']);
        $this->assertCount(2, $result['criteria'][0]['deductions']);
        $this->assertCount(1, $result['criteria'][1]['deductions']);
    }

    public function test_scale_clamp_override_and_rounding_do_not_change_pass_decision(): void
    {
        $rubric = ['pass_threshold' => '80', 'criteria' => [['id' => 'direct', 'name' => 'Langsung', 'method' => 'direct', 'min_score' => '1', 'max_score' => '5', 'weight' => '100', 'min_pass_normalized' => null, 'rules' => []]], 'bands' => []];
        $calculator = new ScoreCalculator;
        $this->assertSame('75.00', $calculator->calculate($rubric, ['direct' => ['direct' => '4']], true)['display_score']);

        $rubric['criteria'][0]['min_score'] = '0';
        $rubric['criteria'][0]['max_score'] = '100';
        $rubric['criteria'][0]['method'] = 'deduction';
        $rubric['criteria'][0]['rules'] = [['id' => 'large', 'name' => 'Potongan besar', 'deduction_points' => '120']];
        $clamped = $calculator->calculate($rubric, ['events' => [['id' => 'one', 'criterion_id' => 'direct', 'rule_id' => 'large', 'active' => true]]], true);
        $this->assertSame('0.00000000', $clamped['criteria'][0]['computed_raw']);

        $rubric['criteria'][0]['method'] = 'direct';
        $rubric['criteria'][0]['rules'] = [];
        $rounded = $calculator->calculate($rubric, ['direct' => ['direct' => '79.999']], true);
        $this->assertSame('80.00', $rounded['display_score']);
        $this->assertFalse($rounded['passed']);

        $overridden = $calculator->calculate($rubric, ['direct' => ['direct' => '50'], 'overrides' => ['direct' => ['raw' => '90', 'reason' => 'Penyesuaian setelah review']]], true);
        $this->assertSame('50.00000000', $overridden['criteria'][0]['computed_raw']);
        $this->assertSame('90.00000000', $overridden['criteria'][0]['effective_raw']);
        $this->assertTrue($overridden['passed']);
    }

    public function test_draft_can_be_incomplete_but_final_cannot_and_override_needs_reason(): void
    {
        $calculator = new ScoreCalculator;
        $draft = $calculator->calculate($this->rubric(), ['direct' => []], false);
        $this->assertNull($draft['display_score']);
        $this->assertFalse($draft['complete']);

        try {
            $calculator->calculate($this->rubric(), ['direct' => []], true);
            $this->fail('Finalisasi tanpa nilai langsung harus ditolak.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('direct.fashahah', $exception->errors());
        }

        try {
            $calculator->calculate($this->rubric(), ['direct' => ['fashahah' => '85'], 'overrides' => ['fashahah' => ['raw' => '90', 'reason' => '']]], true);
            $this->fail('Override tanpa alasan harus ditolak.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('overrides.fashahah.reason', $exception->errors());
        }
    }

    public function test_penalty_event_cannot_be_retried_or_point_to_wrong_rule(): void
    {
        $calculator = new ScoreCalculator;
        $event = ['id' => 'same', 'criterion_id' => 'fluency', 'rule_id' => 'pause', 'active' => true];
        $result = $calculator->calculate($this->rubric(), ['direct' => ['fashahah' => '85'], 'events' => [$event, $event]], true);
        $this->assertSame('95.00000000', $result['criteria'][0]['computed_raw']);

        $this->expectException(ValidationException::class);
        $calculator->calculate($this->rubric(), ['direct' => ['fashahah' => '85'], 'events' => [['id' => 'bad', 'criterion_id' => 'tajwid', 'rule_id' => 'pause', 'active' => true]]], true);
    }

    public function test_same_event_id_with_different_payload_is_rejected(): void
    {
        $event = ['id' => 'same', 'criterion_id' => 'fluency', 'rule_id' => 'pause', 'active' => true];
        $this->expectException(ValidationException::class);
        (new ScoreCalculator)->calculate($this->rubric(), ['direct' => ['fashahah' => '85'], 'events' => [$event, [...$event, 'active' => false]]], true);
    }
}
