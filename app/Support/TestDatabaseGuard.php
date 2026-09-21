<?php

namespace App\Support;

use RuntimeException;

class TestDatabaseGuard
{
    public static function assertSafe(array $config): void
    {
        $connection = $config['connections']['testing'] ?? [];
        if (($config['default'] ?? null) !== 'testing'
            || ($connection['driver'] ?? null) !== 'mysql'
            || ($connection['host'] ?? null) !== 'lerd-mysql'
            || (string) ($connection['port'] ?? '') !== '3306'
            || ($connection['database'] ?? null) !== 'penilaian_tahfidz_testing'
            || ! empty($connection['url'])
            || ! empty($connection['unix_socket'])
            || isset($connection['read']) || isset($connection['write'])) {
            throw new RuntimeException('Test ditolak: wajib memakai database MySQL penilaian_tahfidz_testing di lerd-mysql:3306.');
        }
    }
}
