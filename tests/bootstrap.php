<?php

require dirname(__DIR__).'/vendor/autoload.php';

$cache = getenv('APP_CONFIG_CACHE') ?: dirname(__DIR__).'/bootstrap/cache/config.php';
if (file_exists($cache)) {
    throw new RuntimeException('Test ditolak: jalankan lerd artisan config:clear dahulu.');
}
if (($_SERVER['APP_ENV'] ?? $_ENV['APP_ENV'] ?? getenv('APP_ENV')) !== 'testing') {
    throw new RuntimeException('Test ditolak: APP_ENV harus testing.');
}
