<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\PrivateBackups;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class PrivateBackupTest extends TestCase
{
    use RefreshDatabase;

    private const PASSWORD = 'kata sandi uji sangat panjang';

    private function archiveFile(string $bytes): UploadedFile
    {
        return UploadedFile::fake()->createWithContent('backup.pthbackup', $bytes);
    }

    public function test_encrypted_round_trip_restores_data_and_keeps_a_pre_restore_backup(): void
    {
        Storage::fake('local');
        $owner = User::factory()->create();
        $student = (string) Str::ulid();
        DB::table('students')->insert(['id' => $student, 'owner_id' => $owner->id, 'code' => 'S-1', 'name' => 'Nama sebelum backup']);
        $service = app(PrivateBackups::class);
        $backup = $service->create($owner->id, self::PASSWORD);
        $bytes = file_get_contents($service->downloadPath($owner->id, $backup['id']));
        $this->assertStringNotContainsString('Nama sebelum backup', $bytes);
        DB::table('students')->where('id', $student)->update(['name' => 'Nama setelah backup']);

        $stage = $service->stage($owner->id, $this->archiveFile($bytes), self::PASSWORD);
        $this->assertSame(1, $stage['impact']['current']['students']);
        $this->assertSame(1, $stage['impact']['incoming']['students']);
        $result = $service->restore($owner->id, $stage['id'], self::PASSWORD);

        $this->assertSame('Nama sebelum backup', DB::table('students')->where('id', $student)->value('name'));
        $this->assertSame('complete', DB::table('backup_runs')->where('id', $stage['id'])->value('status'));
        $this->assertNotNull($service->downloadPath($owner->id, $result['pre_restore_backup_id']));
        $this->assertFalse(app()->isDownForMaintenance());
    }

    public function test_wrong_password_corruption_and_schema_mismatch_leave_active_data_unchanged(): void
    {
        Storage::fake('local');
        $owner = User::factory()->create();
        $service = app(PrivateBackups::class);
        $backup = $service->create($owner->id, self::PASSWORD);
        $bytes = file_get_contents($service->downloadPath($owner->id, $backup['id']));
        $id = (string) Str::ulid();
        DB::table('students')->insert(['id' => $id, 'owner_id' => $owner->id, 'code' => 'S-2', 'name' => 'Tetap ada']);

        foreach (['wrong password' => $bytes, 'corrupt' => substr_replace($bytes, 'x', -2, 1)] as $password => $payload) {
            try {
                $service->stage($owner->id, $this->archiveFile($payload), $password === 'corrupt' ? self::PASSWORD : $password);
                $this->fail('Arsip seharusnya ditolak.');
            } catch (ValidationException) {
                $this->assertSame('Tetap ada', DB::table('students')->where('id', $id)->value('name'));
            }
        }

        DB::table('migrations')->insert(['migration' => '9999_99_99_999999_incompatible', 'batch' => 999]);
        try {
            $service->stage($owner->id, $this->archiveFile($bytes), self::PASSWORD);
            $this->fail('Skema berbeda seharusnya ditolak.');
        } catch (ValidationException) {
            $this->assertSame('Tetap ada', DB::table('students')->where('id', $id)->value('name'));
        }
    }

    public function test_routes_are_owner_scoped_and_require_confirmation(): void
    {
        Storage::fake('local');
        $owner = User::factory()->create();
        $service = app(PrivateBackups::class);
        $backup = $service->create($owner->id, self::PASSWORD);
        $this->get('/backups')->assertRedirect('/login');
        $this->actingAs($owner)->get('/backups')->assertOk();
        $this->post('/backups/restores/unknown/confirm', ['password' => self::PASSWORD, 'confirmation' => 'salah'])->assertSessionHasErrors('confirmation');
        $other = User::factory()->make(['id' => 999]);
        $this->actingAs($other)->get('/backups/'.$backup['id'].'/download')->assertNotFound();
    }

    public function test_manifest_tracks_active_and_old_mushaf_editions_and_rejects_source_drift(): void
    {
        Storage::fake('local');
        $owner = User::factory()->create();
        $dataset = (string) Str::ulid();
        DB::table('quran_datasets')->insert(['id' => $dataset, 'source_name' => 'Fixture sintetis F7', 'version' => 'test-only', 'riwayah' => 'Hafs', 'checksum' => str_repeat('a', 64), 'validation_status' => 'active']);
        $old = (string) Str::ulid();
        $active = (string) Str::ulid();
        DB::table('mushaf_editions')->insert([
            ['id' => $old, 'dataset_id' => $dataset, 'name' => 'Lama sintetis', 'version' => 'test-only-1', 'status' => 'retired', 'checksum' => str_repeat('b', 64)],
            ['id' => $active, 'dataset_id' => $dataset, 'name' => 'Aktif sintetis', 'version' => 'test-only-2', 'status' => 'active', 'checksum' => str_repeat('c', 64)],
        ]);
        $service = app(PrivateBackups::class);
        $backup = $service->create($owner->id, self::PASSWORD);
        $bytes = file_get_contents($service->downloadPath($owner->id, $backup['id']));
        $stage = $service->stage($owner->id, $this->archiveFile($bytes), self::PASSWORD);
        $this->assertCount(2, $stage['manifest']['source_refs']['editions']);

        DB::table('mushaf_editions')->where('id', $old)->update(['checksum' => str_repeat('d', 64)]);
        $this->expectException(ValidationException::class);
        $service->restore($owner->id, $stage['id'], self::PASSWORD);
    }

    public function test_database_failure_during_restore_rolls_back_deleted_rows_and_exits_maintenance(): void
    {
        Storage::fake('local');
        $owner = User::factory()->create();
        $student = (string) Str::ulid();
        DB::table('students')->insert(['id' => $student, 'owner_id' => $owner->id, 'code' => 'S-3', 'name' => 'Data aktif aman']);
        $service = app(PrivateBackups::class);
        $envelope = (new \ReflectionMethod($service, 'snapshot'))->invoke($service, $owner->id);
        $envelope['data']['students'][0]['name'] = str_repeat('X', 1000);
        $envelope['manifest']['data_sha256'] = hash('sha256', json_encode($envelope['data'], JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE));
        $bytes = (new \ReflectionMethod($service, 'encrypt'))->invoke($service, $envelope, self::PASSWORD);
        $stage = $service->stage($owner->id, $this->archiveFile($bytes), self::PASSWORD);

        try {
            $service->restore($owner->id, $stage['id'], self::PASSWORD);
            $this->fail('Insert yang tidak valid seharusnya gagal.');
        } catch (QueryException) {
            $this->assertSame('Data aktif aman', DB::table('students')->where('id', $student)->value('name'));
            $this->assertSame('failed', DB::table('backup_runs')->where('id', $stage['id'])->value('status'));
            $this->assertFalse(app()->isDownForMaintenance());
        }
    }
}
