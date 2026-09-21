<?php

namespace Tests\Unit;

use App\Support\TestDatabaseGuard;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Symfony\Component\Process\Process;

class TestDatabaseGuardTest extends TestCase
{
    private function safeConfig(): array
    {
        return ['default' => 'testing', 'connections' => ['testing' => [
            'driver' => 'mysql', 'host' => 'lerd-mysql', 'port' => 3306,
            'database' => 'penilaian_tahfidz_testing',
        ]]];
    }

    public function test_accepts_only_the_isolated_connection(): void
    {
        TestDatabaseGuard::assertSafe($this->safeConfig());
        $this->addToAssertionCount(1);
    }

    public function test_rejects_application_default(): void
    {
        $config = $this->safeConfig();
        $config['default'] = 'mysql';
        $this->expectException(RuntimeException::class);
        TestDatabaseGuard::assertSafe($config);
    }

    public function test_rejects_application_database(): void
    {
        $config = $this->safeConfig();
        $config['connections']['testing']['database'] = 'penilaian_tahfidz';
        $this->expectException(RuntimeException::class);
        TestDatabaseGuard::assertSafe($config);
    }

    public function test_rejects_url_override(): void
    {
        $config = $this->safeConfig();
        $config['connections']['testing']['url'] = 'mysql://localhost/application';
        $this->expectException(RuntimeException::class);
        TestDatabaseGuard::assertSafe($config);
    }

    public function test_rejects_remote_host(): void
    {
        $config = $this->safeConfig();
        $config['connections']['testing']['host'] = 'production';
        $this->expectException(RuntimeException::class);
        TestDatabaseGuard::assertSafe($config);
    }

    public function test_bootstrap_refuses_cached_configuration_before_app_boot(): void
    {
        $cache = tempnam(sys_get_temp_dir(), 'tahfidz-cache-guard-');
        try {
            file_put_contents($cache, '<?php return [];');
            $process = new Process([PHP_BINARY, dirname(__DIR__).'/bootstrap.php'], null, [
                'APP_CONFIG_CACHE' => $cache,
                'APP_ENV' => 'testing',
            ]);
            $process->run();
            $this->assertFalse($process->isSuccessful());
            $this->assertStringContainsString('Test ditolak', $process->getOutput().$process->getErrorOutput());
        } finally {
            unlink($cache);
        }
    }
}
