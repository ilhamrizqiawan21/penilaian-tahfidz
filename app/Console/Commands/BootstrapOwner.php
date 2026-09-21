<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password;

class BootstrapOwner extends Command
{
    protected $signature = 'tahfidz:owner';

    protected $description = 'Buat satu akun pemilik melalui prompt aman (tanpa kata sandi dalam argumen)';

    public function handle(): int
    {
        if (User::exists()) {
            $this->error('Pemilik sudah tersedia. Akun tidak diubah.');

            return self::FAILURE;
        }

        if (! $this->input->isInteractive()) {
            $this->error('Jalankan perintah secara interaktif untuk mengisi kredensial.');

            return self::FAILURE;
        }

        $data = [
            'name' => trim((string) $this->ask('Nama pemilik')),
            'email' => Str::lower(trim((string) $this->ask('Email pemilik'))),
            'password' => $this->secret('Kata sandi (minimal 12 karakter, huruf besar/kecil, angka, simbol)', false),
            'password_confirmation' => $this->secret('Ulangi kata sandi', false),
        ];
        $validator = Validator::make($data, [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255'],
            'password' => ['required', 'string', 'max:128', 'confirmed', Password::min(12)->mixedCase()->numbers()->symbols()],
        ]);

        if ($validator->fails()) {
            $this->error('Data tidak valid. Periksa nama, email, serta kekuatan dan konfirmasi kata sandi.');

            return self::FAILURE;
        }

        try {
            User::create(collect($data)->except('password_confirmation')->all());
        } catch (QueryException $exception) {
            if (! User::exists()) {
                throw $exception;
            }
            $this->error('Pemilik sudah dibuat oleh proses lain. Akun tidak diubah.');

            return self::FAILURE;
        }

        $this->info('Akun pemilik berhasil dibuat. Silakan masuk.');

        return self::SUCCESS;
    }
}
