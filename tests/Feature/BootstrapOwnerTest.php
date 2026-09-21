<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Tests\TestCase;

class BootstrapOwnerTest extends TestCase
{
    use RefreshDatabase;

    public function test_interactive_bootstrap_creates_a_hashed_owner(): void
    {
        $password = Str::random(24).'aA1!';
        $this->artisan('tahfidz:owner')
            ->expectsQuestion('Nama pemilik', 'Guru Uji')
            ->expectsQuestion('Email pemilik', ' OWNER@EXAMPLE.TEST ')
            ->expectsQuestion('Kata sandi (minimal 12 karakter, huruf besar/kecil, angka, simbol)', $password)
            ->expectsQuestion('Ulangi kata sandi', $password)
            ->expectsOutput('Akun pemilik berhasil dibuat. Silakan masuk.')
            ->assertSuccessful();
        $owner = User::sole();
        $this->assertSame('owner@example.test', $owner->email);
        $this->assertSame('Asia/Jakarta', $owner->timezone);
        $this->assertTrue(Hash::check($password, $owner->password));
    }

    public function test_second_bootstrap_does_not_change_existing_owner(): void
    {
        $owner = User::factory()->create()->fresh();
        $this->artisan('tahfidz:owner')->expectsOutput('Pemilik sudah tersedia. Akun tidak diubah.')->assertFailed();
        $this->assertSame($owner->getAttributes(), User::sole()->getAttributes());
    }

    public function test_bootstrap_rejects_noninteractive_invocation(): void
    {
        $this->artisan('tahfidz:owner', ['--no-interaction' => true])->assertFailed();
        $this->assertDatabaseCount('users', 0);
    }

    public function test_bootstrap_rejects_weak_password(): void
    {
        $this->artisan('tahfidz:owner')
            ->expectsQuestion('Nama pemilik', 'Guru Uji')
            ->expectsQuestion('Email pemilik', 'owner@example.test')
            ->expectsQuestion('Kata sandi (minimal 12 karakter, huruf besar/kecil, angka, simbol)', 'weak')
            ->expectsQuestion('Ulangi kata sandi', 'weak')
            ->assertFailed();
        $this->assertDatabaseCount('users', 0);
    }

    public function test_database_rejects_a_second_owner_even_outside_the_command(): void
    {
        User::factory()->create();
        $this->expectException(QueryException::class);
        User::factory()->create();
    }

    public function test_database_rejects_bypassing_the_owner_slot(): void
    {
        $user = User::factory()->create();
        $this->expectException(QueryException::class);
        DB::table('users')->where('id', $user->id)->update(['owner_slot' => 2]);
    }
}
