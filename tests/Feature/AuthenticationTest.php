<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class AuthenticationTest extends TestCase
{
    use RefreshDatabase;

    public function test_database_is_isolated_and_application_connections_are_unavailable(): void
    {
        $this->assertSame('testing', DB::getDefaultConnection());
        $this->assertSame('penilaian_tahfidz_testing', DB::selectOne('SELECT DATABASE() AS db')->db);
        $this->assertSame(['testing'], array_keys(config('database.connections')));
        $this->assertFalse($this->app->configurationIsCached());
    }

    public function test_guest_must_login_and_registration_is_not_available(): void
    {
        $this->get('/dashboard')->assertRedirect('/login');
        $this->get('/mushaf/prototype')->assertRedirect('/login');
        $this->get('/register')->assertNotFound();
        $this->post('/register', ['email' => 'stranger@example.test'])->assertNotFound();
        $this->get('/login')->assertOk()->assertInertia(fn (Assert $page) => $page->component('Auth/Login')->where('auth.user', null));
    }

    public function test_owner_can_login_with_normalized_email_and_logout(): void
    {
        $password = Str::random(32);
        $user = User::factory()->create(['email' => 'owner@example.test', 'password' => $password]);
        $this->withSession(['marker' => 'old-session']);
        $sessionId = session()->getId();
        $this->post('/login', ['email' => ' OWNER@EXAMPLE.TEST ', 'password' => $password])->assertRedirect('/dashboard');
        $this->assertAuthenticatedAs($user);
        $this->assertNotSame($sessionId, session()->getId());
        $this->get('/dashboard')->assertOk()->assertInertia(fn (Assert $page) => $page
            ->component('Dashboard')->where('auth.user.email', $user->email)
            ->missing('auth.user.password')->missing('auth.user.remember_token'));
        $token = session()->token();
        $this->post('/logout')->assertRedirect('/login');
        $this->assertGuest();
        $this->assertNull(session('marker'));
        $this->assertNotSame($token, session()->token());
        $this->get('/dashboard')->assertRedirect('/login');
    }

    public function test_wrong_password_is_generic_and_is_never_flashed(): void
    {
        User::factory()->create(['email' => 'owner@example.test']);
        $this->from('/login')->post('/login', ['email' => 'owner@example.test', 'password' => Str::random(32)])
            ->assertRedirect('/login')->assertSessionHasErrors(['email' => 'Email atau kata sandi tidak sesuai.'])
            ->assertSessionMissing('_old_input.password');
        $this->assertGuest();
    }

    public function test_login_is_throttled_after_five_failures(): void
    {
        $password = Str::random(32);
        User::factory()->create(['email' => 'owner@example.test', 'password' => $password]);
        for ($attempt = 0; $attempt < 5; $attempt++) {
            $this->post('/login', ['email' => 'owner@example.test', 'password' => 'invalid'])->assertSessionHasErrors('email');
        }
        $this->post('/login', ['email' => 'owner@example.test', 'password' => $password])
            ->assertSessionHasErrors(['email' => 'Terlalu banyak percobaan. Coba lagi dalam satu menit.']);
        $this->assertGuest();
        $this->travel(61)->seconds();
        $this->post('/login', ['email' => 'owner@example.test', 'password' => $password])->assertRedirect('/dashboard');
        $this->assertAuthenticated();
    }

    public function test_seeder_does_not_create_demo_accounts(): void
    {
        $this->seed();
        $this->assertDatabaseCount('users', 0);
    }

    public function test_password_is_hashed_and_private_pages_are_not_cached(): void
    {
        $password = Str::random(32);
        $user = User::factory()->create(['password' => $password]);
        $this->assertNotSame($password, $user->password);
        $this->assertTrue(Hash::check($password, $user->password));
        $response = $this->actingAs($user)->get('/dashboard');
        $this->assertStringContainsString('no-store', $response->headers->get('Cache-Control'));
        $response->assertHeader('X-Frame-Options', 'DENY');
        $this->actingAs($user)->get('/quran')->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('Quran/Reader'));
    }
}
