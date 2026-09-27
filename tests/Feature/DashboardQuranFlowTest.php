<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\DemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class DashboardQuranFlowTest extends TestCase
{
    use RefreshDatabase;

    public function test_dashboard_presents_real_workflow_state(): void
    {
        $owner = User::factory()->create(['email' => 'demo@penilaian-tahfidz.test']);
        $this->seed(DemoSeeder::class);

        $this->actingAs($owner)->get('/dashboard')->assertOk()->assertInertia(fn (Assert $page) => $page
            ->component('Dashboard')
            ->where('summary.students', 12)
            ->where('summary.active_drafts', 1)
            ->where('summary.final_assessments', 7)
            ->where('setup.reference_ready', true)
            ->has('recent_sessions')
        );
    }

    public function test_quran_reader_uses_active_reference_and_old_prototype_redirects(): void
    {
        $owner = User::factory()->create(['email' => 'demo@penilaian-tahfidz.test']);
        $this->seed(DemoSeeder::class);

        $this->actingAs($owner)->get('/quran')->assertOk()->assertInertia(fn (Assert $page) => $page
            ->component('Quran/Reader')
            ->where('reference.is_demo', true)
            ->where('surahs.0.number', 1)
            ->where('ayahs.0.text_uthmani', 'Teks sintetis demo 1:1 — bukan ayat Al-Qur’an')
            ->has('ayahs.0.words', 4)
        );
        $this->actingAs($owner)->get('/mushaf/prototype')->assertRedirect('/quran');
    }
}
