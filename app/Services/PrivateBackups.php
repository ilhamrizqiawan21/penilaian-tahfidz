<?php

namespace App\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use RuntimeException;

class PrivateBackups
{
    private const MAGIC = "PTHF7\x01";

    private const FORMAT = 1;

    private const MAX_ARCHIVE_BYTES = 64 * 1024 * 1024;

    private const MAX_JSON_BYTES = 128 * 1024 * 1024;

    // Ordered by foreign-key dependencies. Quran source tables and users are intentionally excluded.
    private const TABLES = [
        'students', 'study_groups', 'group_memberships', 'activity_types',
        'programs', 'program_versions', 'program_ranges', 'enrollments',
        'rubrics', 'rubric_versions', 'criteria', 'mistake_rules', 'grade_bands',
        'assessment_records', 'assessments', 'assessment_ranges', 'criterion_scores',
        'annotations', 'mutation_receipts', 'audit_events',
    ];

    public function list(int $ownerId): array
    {
        $this->discardExpiredStages();

        return DB::table('backup_runs')->where('owner_id', $ownerId)->where('operation', 'backup')
            ->where('status', 'ready')->orderByDesc('started_at')->limit(30)
            ->get(['id', 'started_at', 'checksum', 'manifest', 'pre_restore_backup_id'])->all();
    }

    public function create(int $ownerId, string $password, bool $preRestore = false): array
    {
        $envelope = DB::transaction(fn () => $this->snapshot($ownerId));
        $bytes = $this->encrypt($envelope, $password);
        if (strlen($bytes) > self::MAX_ARCHIVE_BYTES) {
            throw ValidationException::withMessages(['password' => 'Arsip melebihi batas 64 MB.']);
        }
        $id = (string) Str::ulid();
        $key = "backups/$id.pthbackup";
        if (! Storage::disk('local')->put($key, $bytes)) {
            throw new RuntimeException('Gagal menyimpan backup privat.');
        }
        $checksum = hash('sha256', $bytes);
        try {
            DB::table('backup_runs')->insert([
                'id' => $id, 'owner_id' => $ownerId, 'operation' => 'backup', 'status' => 'ready',
                'storage_key' => $key, 'manifest' => json_encode($envelope['manifest'], JSON_THROW_ON_ERROR),
                'checksum' => $checksum, 'started_at' => now(), 'completed_at' => now(),
            ]);
        } catch (\Throwable $error) {
            Storage::disk('local')->delete($key);
            throw $error;
        }

        return ['id' => $id, 'checksum' => $checksum, 'pre_restore' => $preRestore];
    }

    public function downloadPath(int $ownerId, string $id): string
    {
        $row = DB::table('backup_runs')->where('id', $id)->where('owner_id', $ownerId)
            ->where('operation', 'backup')->where('status', 'ready')->first() ?? abort(404);
        $disk = Storage::disk('local');
        abort_unless($row->storage_key && $disk->exists($row->storage_key), 404);
        $path = $disk->path($row->storage_key);
        abort_unless(hash_equals($row->checksum, hash_file('sha256', $path)), 409, 'Checksum arsip tidak cocok.');

        return $path;
    }

    public function stage(int $ownerId, UploadedFile $file, string $password): array
    {
        $this->discardExpiredStages();
        $bytes = file_get_contents($file->getRealPath());
        if ($bytes === false || strlen($bytes) > self::MAX_ARCHIVE_BYTES) {
            throw ValidationException::withMessages(['archive' => 'Arsip terlalu besar atau tidak dapat dibaca.']);
        }
        $envelope = $this->decrypt($bytes, $password);
        $this->validateEnvelope($envelope);
        $id = (string) Str::ulid();
        $key = "backups/staged/$id.pthbackup";
        if (! Storage::disk('local')->put($key, $bytes)) {
            throw new RuntimeException('Gagal menyimpan arsip pada staging privat.');
        }
        $impact = $this->impact($envelope);
        try {
            DB::table('backup_runs')->insert([
                'id' => $id, 'owner_id' => $ownerId, 'operation' => 'restore', 'status' => 'staged',
                'storage_key' => $key, 'manifest' => json_encode($envelope['manifest'], JSON_THROW_ON_ERROR),
                'checksum' => hash('sha256', $bytes), 'started_at' => now(),
            ]);
        } catch (\Throwable $error) {
            Storage::disk('local')->delete($key);
            throw $error;
        }

        return ['id' => $id, 'impact' => $impact, 'manifest' => $envelope['manifest']];
    }

    public function staged(int $ownerId, string $id): array
    {
        $row = DB::table('backup_runs')->where('id', $id)->where('owner_id', $ownerId)
            ->where('operation', 'restore')->where('status', 'staged')->first() ?? abort(404);
        abort_if($row->started_at < now()->subMinutes(30)->toDateTimeString(), 410, 'Pratinjau restore kedaluwarsa.');

        $manifest = json_decode($row->manifest, true, 512, JSON_THROW_ON_ERROR);
        $current = [];
        foreach (self::TABLES as $table) {
            $current[$table] = DB::table($table)->count();
        }

        return ['id' => $id, 'manifest' => $manifest, 'checksum' => $row->checksum,
            'impact' => ['current' => $current, 'incoming' => $manifest['table_counts']]];
    }

    public function restore(int $ownerId, string $id, string $password): array
    {
        $stage = $this->staged($ownerId, $id);
        $row = DB::table('backup_runs')->where('id', $id)->where('owner_id', $ownerId)->first();
        $bytes = Storage::disk('local')->get($row->storage_key);
        if (! $bytes || ! hash_equals($stage['checksum'], hash('sha256', $bytes))) {
            throw ValidationException::withMessages(['archive' => 'Arsip staging berubah atau hilang.']);
        }
        $envelope = $this->decrypt($bytes, $password);
        $this->validateEnvelope($envelope);
        $claimed = DB::table('backup_runs')->where('id', $id)->where('owner_id', $ownerId)
            ->where('status', 'staged')->update(['status' => 'preparing']);
        abort_unless($claimed === 1, 409, 'Restore sudah diproses.');
        $alreadyDown = app()->isDownForMaintenance();
        $maintenanceStarted = false;

        try {
            $pre = $this->create($ownerId, $password, true);
            DB::table('backup_runs')->where('id', $id)->update(['pre_restore_backup_id' => $pre['id'], 'status' => 'restoring']);
            if (! $alreadyDown) {
                if (Artisan::call('down') !== 0) {
                    throw new RuntimeException('Gagal mengaktifkan mode pemeliharaan.');
                }
                $maintenanceStarted = true;
            }
            DB::transaction(fn () => $this->replaceData($ownerId, $envelope['data']));
            DB::table('backup_runs')->where('id', $id)->update(['status' => 'complete', 'storage_key' => null, 'completed_at' => now()]);
            Storage::disk('local')->delete($row->storage_key);

            return ['id' => $id, 'pre_restore_backup_id' => $pre['id']];
        } catch (\Throwable $error) {
            DB::table('backup_runs')->where('id', $id)->update(['status' => 'failed', 'error_code' => 'restore_failed', 'storage_key' => null, 'completed_at' => now()]);
            Storage::disk('local')->delete($row->storage_key);
            throw $error;
        } finally {
            if ($maintenanceStarted) {
                Artisan::call('up');
            }
        }
    }

    private function discardExpiredStages(): void
    {
        $expired = DB::table('backup_runs')->where('operation', 'restore')->where('status', 'staged')
            ->where('started_at', '<', now()->subMinutes(30))->get(['id', 'storage_key']);
        foreach ($expired as $row) {
            $changed = DB::table('backup_runs')->where('id', $row->id)->where('status', 'staged')
                ->update(['status' => 'expired', 'storage_key' => null, 'completed_at' => now()]);
            if ($changed === 1 && $row->storage_key) {
                Storage::disk('local')->delete($row->storage_key);
            }
        }
    }

    private function snapshot(int $ownerId): array
    {
        abort_unless(DB::table('users')->count() === 1 && DB::table('users')->where('id', $ownerId)->exists(), 409, 'Backup membutuhkan instalasi satu pemilik.');
        $tables = [];
        foreach (self::TABLES as $table) {
            $tables[$table] = DB::table($table)->orderBy('id')->get()->map(fn ($row) => (array) $row)->all();
        }
        $dataJson = json_encode($tables, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE);
        if (strlen($dataJson) > self::MAX_JSON_BYTES) {
            throw ValidationException::withMessages(['password' => 'Data aplikasi melebihi batas backup.']);
        }

        return [
            'manifest' => [
                'format' => self::FORMAT, 'app_version' => 'f7-1', 'schema' => $this->schemaFingerprint(),
                'created_at' => now()->toISOString(), 'source_refs' => $this->sourceRefs(),
                'table_counts' => array_map('count', $tables), 'data_sha256' => hash('sha256', $dataJson),
                'exclusions' => ['users/passwords', 'app_key', 'mushaf_text_font_layout', 'backup_runs'],
            ],
            'data' => $tables,
        ];
    }

    private function schemaFingerprint(): string
    {
        $migrations = DB::table('migrations')->orderBy('migration')->pluck('migration')->all();

        return hash('sha256', json_encode($migrations, JSON_THROW_ON_ERROR));
    }

    private function sourceRefs(): array
    {
        return [
            'datasets' => DB::table('quran_datasets')->orderBy('id')->get(['id', 'checksum'])->map(fn ($row) => (array) $row)->all(),
            'editions' => DB::table('mushaf_editions')->orderBy('id')->get(['id', 'checksum'])->map(fn ($row) => (array) $row)->all(),
        ];
    }

    private function encrypt(array $envelope, string $password): string
    {
        $json = json_encode($envelope, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE);
        $salt = random_bytes(SODIUM_CRYPTO_PWHASH_SALTBYTES);
        $nonce = random_bytes(SODIUM_CRYPTO_AEAD_XCHACHA20POLY1305_IETF_NPUBBYTES);
        $header = self::MAGIC.$salt.$nonce;
        $key = sodium_crypto_pwhash(SODIUM_CRYPTO_AEAD_XCHACHA20POLY1305_IETF_KEYBYTES, $password, $salt,
            SODIUM_CRYPTO_PWHASH_OPSLIMIT_INTERACTIVE, SODIUM_CRYPTO_PWHASH_MEMLIMIT_INTERACTIVE, SODIUM_CRYPTO_PWHASH_ALG_ARGON2ID13);
        try {
            return $header.sodium_crypto_aead_xchacha20poly1305_ietf_encrypt(gzencode($json, 6), $header, $nonce, $key);
        } finally {
            sodium_memzero($key);
        }
    }

    private function decrypt(string $bytes, string $password): array
    {
        $length = strlen(self::MAGIC) + SODIUM_CRYPTO_PWHASH_SALTBYTES + SODIUM_CRYPTO_AEAD_XCHACHA20POLY1305_IETF_NPUBBYTES;
        if (strlen($bytes) < $length + SODIUM_CRYPTO_AEAD_XCHACHA20POLY1305_IETF_ABYTES || strlen($bytes) > self::MAX_ARCHIVE_BYTES || ! str_starts_with($bytes, self::MAGIC)) {
            throw ValidationException::withMessages(['archive' => 'Format arsip tidak valid.']);
        }
        $header = substr($bytes, 0, $length);
        $salt = substr($bytes, strlen(self::MAGIC), SODIUM_CRYPTO_PWHASH_SALTBYTES);
        $nonce = substr($bytes, strlen(self::MAGIC) + SODIUM_CRYPTO_PWHASH_SALTBYTES, SODIUM_CRYPTO_AEAD_XCHACHA20POLY1305_IETF_NPUBBYTES);
        $key = sodium_crypto_pwhash(SODIUM_CRYPTO_AEAD_XCHACHA20POLY1305_IETF_KEYBYTES, $password, $salt,
            SODIUM_CRYPTO_PWHASH_OPSLIMIT_INTERACTIVE, SODIUM_CRYPTO_PWHASH_MEMLIMIT_INTERACTIVE, SODIUM_CRYPTO_PWHASH_ALG_ARGON2ID13);
        try {
            $compressed = sodium_crypto_aead_xchacha20poly1305_ietf_decrypt(substr($bytes, $length), $header, $nonce, $key);
        } finally {
            sodium_memzero($key);
        }
        if ($compressed === false) {
            throw ValidationException::withMessages(['password' => 'Kata sandi salah atau arsip rusak.']);
        }
        $json = gzdecode($compressed, self::MAX_JSON_BYTES);
        if ($json === false) {
            throw ValidationException::withMessages(['archive' => 'Isi arsip rusak atau terlalu besar.']);
        }
        try {
            $decoded = json_decode($json, true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            throw ValidationException::withMessages(['archive' => 'Isi arsip tidak valid.']);
        }
        if (! is_array($decoded)) {
            throw ValidationException::withMessages(['archive' => 'Isi arsip tidak valid.']);
        }

        return $decoded;
    }

    private function validateEnvelope(array $envelope): void
    {
        $manifest = $envelope['manifest'] ?? null;
        $data = $envelope['data'] ?? null;
        if (! is_array($manifest) || ! is_array($data) || ($manifest['format'] ?? null) !== self::FORMAT || ($manifest['app_version'] ?? null) !== 'f7-1') {
            throw ValidationException::withMessages(['archive' => 'Versi arsip tidak didukung.']);
        }
        if (! hash_equals($this->schemaFingerprint(), (string) ($manifest['schema'] ?? ''))) {
            throw ValidationException::withMessages(['archive' => 'Schema arsip tidak cocok dengan aplikasi.']);
        }
        if (($manifest['source_refs'] ?? null) !== $this->sourceRefs()) {
            throw ValidationException::withMessages(['archive' => 'Referensi mushaf berbeda atau belum dipulihkan pada instalasi ini.']);
        }
        if (array_keys($data) !== self::TABLES || ! hash_equals((string) ($manifest['data_sha256'] ?? ''), hash('sha256', json_encode($data, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE)))) {
            throw ValidationException::withMessages(['archive' => 'Checksum data tidak cocok.']);
        }
        foreach (self::TABLES as $table) {
            if (! is_array($data[$table]) || ($manifest['table_counts'][$table] ?? null) !== count($data[$table])) {
                throw ValidationException::withMessages(['archive' => 'Jumlah baris arsip tidak cocok.']);
            }
            $columns = Schema::getColumnListing($table);
            sort($columns);
            foreach ($data[$table] as $row) {
                if (! is_array($row) || array_is_list($row)) {
                    throw ValidationException::withMessages(['archive' => 'Bentuk baris arsip tidak valid.']);
                }
                $keys = array_keys($row);
                sort($keys);
                if ($keys !== $columns) {
                    throw ValidationException::withMessages(['archive' => 'Kolom arsip tidak cocok dengan schema.']);
                }
            }
        }
    }

    private function impact(array $envelope): array
    {
        $current = [];
        foreach (self::TABLES as $table) {
            $current[$table] = DB::table($table)->count();
        }

        return ['current' => $current, 'incoming' => $envelope['manifest']['table_counts']];
    }

    private function replaceData(int $ownerId, array $data): void
    {
        DB::table('assessment_records')->whereNotNull('current_final_id')->update(['current_final_id' => null]);
        DB::table('assessments')->whereNotNull('previous_assessment_id')->update(['previous_assessment_id' => null]);
        DB::table('enrollments')->whereNotNull('previous_enrollment_id')->update(['previous_enrollment_id' => null]);
        foreach (array_reverse(self::TABLES) as $table) {
            DB::table($table)->delete();
        }
        foreach (self::TABLES as $table) {
            $rows = $data[$table];
            foreach ($rows as &$row) {
                if (array_key_exists('owner_id', $row)) {
                    $row['owner_id'] = $ownerId;
                }
                if (array_key_exists('actor_id', $row)) {
                    $row['actor_id'] = $ownerId;
                }
                if ($table === 'assessment_records') {
                    $row['current_final_id'] = null;
                } elseif ($table === 'assessments') {
                    $row['previous_assessment_id'] = null;
                } elseif ($table === 'enrollments') {
                    $row['previous_enrollment_id'] = null;
                }
            }
            unset($row);
            foreach (array_chunk($rows, 100) as $chunk) {
                DB::table($table)->insert($chunk);
            }
        }
        foreach ($data['enrollments'] as $row) {
            if ($row['previous_enrollment_id']) {
                DB::table('enrollments')->where('id', $row['id'])->update(['previous_enrollment_id' => $row['previous_enrollment_id']]);
            }
        }
        foreach ($data['assessments'] as $row) {
            if ($row['previous_assessment_id']) {
                DB::table('assessments')->where('id', $row['id'])->update(['previous_assessment_id' => $row['previous_assessment_id']]);
            }
        }
        foreach ($data['assessment_records'] as $row) {
            if ($row['current_final_id']) {
                DB::table('assessment_records')->where('id', $row['id'])->update(['current_final_id' => $row['current_final_id']]);
            }
        }
    }
}
