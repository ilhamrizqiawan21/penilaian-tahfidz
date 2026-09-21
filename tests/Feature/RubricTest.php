<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class RubricTest extends TestCase
{
    use RefreshDatabase;

    private function payload(): array
    {
        return [
            'name' => 'Rubrik tahfidz', 'pass_threshold' => '80',
            'criteria' => [
                ['name' => 'Kelancaran', 'method' => 'deduction', 'min_score' => '0', 'max_score' => '100', 'weight' => '60', 'min_pass_normalized' => null, 'rules' => [['name' => 'Terhenti', 'severity_label' => 'Ringan', 'deduction_points' => '5']]],
                ['name' => 'Fashahah', 'method' => 'direct', 'min_score' => '1', 'max_score' => '5', 'weight' => '40', 'min_pass_normalized' => null, 'rules' => []],
            ],
            'bands' => [['label' => 'Perlu latihan', 'lower_bound' => '0', 'upper_bound' => '80'], ['label' => 'Baik', 'lower_bound' => '80', 'upper_bound' => '100']],
        ];
    }

    public function test_publishing_validates_weights_scale_rules_thresholds_and_bands(): void
    {
        $owner = User::factory()->create();
        $payload = $this->payload();
        $payload['criteria'][1]['weight'] = '30';
        $this->actingAs($owner)->post('/rubrics', $payload)->assertRedirect('/rubrics');
        $rubric = DB::table('rubrics')->sole();
        $version = DB::table('rubric_versions')->sole();
        $this->actingAs($owner)->post("/rubrics/{$rubric->id}/versions/{$version->id}/publish")->assertSessionHasErrors('criteria');

        $payload['criteria'][1]['weight'] = '40';
        $payload['criteria'][1]['max_score'] = '1';
        $this->actingAs($owner)->put("/rubrics/{$rubric->id}/versions/{$version->id}", $payload)->assertRedirect('/rubrics');
        $this->actingAs($owner)->post("/rubrics/{$rubric->id}/versions/{$version->id}/publish")->assertSessionHasErrors('criteria.1.max_score');

        $payload['criteria'][1]['max_score'] = '5';
        $payload['bands'][1]['lower_bound'] = '79';
        $this->actingAs($owner)->put("/rubrics/{$rubric->id}/versions/{$version->id}", $payload)->assertRedirect('/rubrics');
        $this->actingAs($owner)->post("/rubrics/{$rubric->id}/versions/{$version->id}/publish")->assertSessionHasErrors('bands');

        $payload['bands'][1]['lower_bound'] = '80';
        $this->actingAs($owner)->put("/rubrics/{$rubric->id}/versions/{$version->id}", $payload)->assertRedirect('/rubrics');
        $this->actingAs($owner)->post("/rubrics/{$rubric->id}/versions/{$version->id}/publish")->assertRedirect('/rubrics');
        $this->assertSame('published', DB::table('rubric_versions')->where('id', $version->id)->value('status'));
    }

    public function test_clone_preserves_published_history_and_owner_boundary(): void
    {
        $owner = User::factory()->create();
        $this->actingAs($owner)->post('/rubrics', $this->payload());
        $rubric = DB::table('rubrics')->sole();
        $first = DB::table('rubric_versions')->sole();
        $this->actingAs($owner)->post("/rubrics/{$rubric->id}/versions/{$first->id}/publish");
        $this->actingAs($owner)->put("/rubrics/{$rubric->id}/versions/{$first->id}", $this->payload())->assertStatus(409);
        $this->actingAs($owner)->post("/rubrics/{$rubric->id}/versions")->assertRedirect('/rubrics');
        $second = DB::table('rubric_versions')->where('version', 2)->sole();
        $this->assertDatabaseCount('criteria', 4);
        $this->assertDatabaseCount('mistake_rules', 2);
        $this->assertDatabaseCount('grade_bands', 4);
        $changed = $this->payload();
        $changed['name'] = 'Rubrik lanjut';
        $this->actingAs($owner)->put("/rubrics/{$rubric->id}/versions/{$second->id}", $changed)->assertRedirect('/rubrics');
        $this->actingAs($owner)->post("/rubrics/{$rubric->id}/versions/{$second->id}/publish")->assertRedirect('/rubrics');
        $this->actingAs($owner)->get('/rubrics')->assertOk()->assertInertia(fn (Assert $page) => $page->component('Rubrics/Index')->has('rubrics', 1)->has('rubrics.0.versions', 2)->has('rubrics.0.versions.0.criteria', 2)->etc());
        $this->assertSame('retired', DB::table('rubric_versions')->where('id', $first->id)->value('status'));
        $this->assertSame('Rubrik tahfidz', DB::table('rubric_versions')->where('id', $first->id)->value('name_snapshot'));
        $this->assertSame('Rubrik lanjut', DB::table('rubric_versions')->where('id', $second->id)->value('name_snapshot'));
        $other = User::factory()->make(['id' => 999]);
        $this->actingAs($other)->post("/rubrics/{$rubric->id}/versions")->assertNotFound();
    }

    public function test_preview_uses_published_rubric_and_rejects_foreign_owner(): void
    {
        $owner = User::factory()->create();
        $this->actingAs($owner)->post('/rubrics', $this->payload());
        $rubric = DB::table('rubrics')->sole();
        $version = DB::table('rubric_versions')->sole();
        $this->actingAs($owner)->postJson("/rubrics/{$rubric->id}/versions/{$version->id}/preview", [])->assertConflict();
        $this->actingAs($owner)->post("/rubrics/{$rubric->id}/versions/{$version->id}/publish");
        $criteria = DB::table('criteria')->where('rubric_version_id', $version->id)->orderBy('sort_order')->get();
        $rule = DB::table('mistake_rules')->where('criterion_id', $criteria[0]->id)->sole();
        $this->actingAs($owner)->postJson("/rubrics/{$rubric->id}/versions/{$version->id}/preview", [
            'direct' => [$criteria[1]->id => '4'],
            'events' => [
                ['id' => 'once', 'criterion_id' => $criteria[0]->id, 'rule_id' => $rule->id, 'active' => true],
                ['id' => 'once', 'criterion_id' => $criteria[0]->id, 'rule_id' => $rule->id, 'active' => true],
                ['id' => 'note', 'kind' => 'note', 'active' => true],
                ['id' => 'undone', 'criterion_id' => $criteria[0]->id, 'rule_id' => $rule->id, 'active' => false],
            ],
            'final' => true,
        ])->assertOk()->assertJsonPath('display_score', '87.00')->assertJsonPath('criteria.0.deductions.0.event_id', 'once');
        $this->actingAs($owner)->postJson("/rubrics/{$rubric->id}/versions/{$version->id}/preview", ['final' => true])->assertUnprocessable()->assertJsonValidationErrors("direct.{$criteria[1]->id}");
        $other = User::factory()->make(['id' => 999]);
        $this->actingAs($other)->postJson("/rubrics/{$rubric->id}/versions/{$version->id}/preview", [])->assertNotFound();
    }

    public function test_direct_rule_and_zero_deduction_cannot_publish_and_archive_preserves_history(): void
    {
        $owner = User::factory()->create();
        $payload = $this->payload();
        $payload['criteria'][1]['rules'] = [['name' => 'Tidak sesuai', 'deduction_points' => '2']];
        $this->actingAs($owner)->post('/rubrics', $payload);
        $rubric = DB::table('rubrics')->sole();
        $version = DB::table('rubric_versions')->sole();
        $this->actingAs($owner)->post("/rubrics/{$rubric->id}/versions/{$version->id}/publish")->assertSessionHasErrors('criteria.1.rules');
        $payload['criteria'][1]['rules'] = [];
        $payload['criteria'][0]['rules'][0]['deduction_points'] = '0';
        $this->actingAs($owner)->put("/rubrics/{$rubric->id}/versions/{$version->id}", $payload);
        $this->actingAs($owner)->post("/rubrics/{$rubric->id}/versions/{$version->id}/publish")->assertSessionHasErrors('criteria.0.rules.0.deduction_points');
        $payload['criteria'][0]['rules'][0]['deduction_points'] = '5';
        $this->actingAs($owner)->put("/rubrics/{$rubric->id}/versions/{$version->id}", $payload);
        $this->actingAs($owner)->post("/rubrics/{$rubric->id}/versions/{$version->id}/publish")->assertRedirect('/rubrics');
        $this->actingAs($owner)->post("/rubrics/{$rubric->id}/archive")->assertRedirect('/rubrics');
        $this->assertNotNull(DB::table('rubrics')->where('id', $rubric->id)->value('archived_at'));
        $this->assertDatabaseCount('rubric_versions', 1);
        $this->actingAs($owner)->post("/rubrics/{$rubric->id}/versions")->assertNotFound();
    }
}
