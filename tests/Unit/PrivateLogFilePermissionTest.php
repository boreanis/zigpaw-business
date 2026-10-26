<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Support\PrivateLogManager;
use Illuminate\Config\Repository;
use Illuminate\Container\Container;
use Illuminate\Foundation\Application;
use Illuminate\Log\Logger;
use Illuminate\Log\LogManager;
use Monolog\Handler\RotatingFileHandler;
use Monolog\Handler\StreamHandler;
use Monolog\Logger as Monolog;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class PrivateLogFilePermissionTest extends TestCase
{
    /** @return array<string, array{string}> */
    public static function fileChannels(): array
    {
        return [
            'single' => ['single'],
            'daily' => ['daily'],
        ];
    }

    #[DataProvider('fileChannels')]
    public function test_file_channel_is_owner_only_with_permissive_umask(string $channel): void
    {
        $this->assertOwnedLog($channel);
    }

    public function test_actual_app_binding_makes_emergency_fallback_owner_only(): void
    {
        $this->assertOwnedLog('synthetic-invalid-channel', true);
    }

    private function assertOwnedLog(string $channel, bool $emergency = false): void
    {
        $previousContainer = Container::getInstance();
        $directory = sys_get_temp_dir().'/zigpaw-private-log-mode-'.bin2hex(random_bytes(16));
        $created = false;
        $logger = null;
        $previousUmask = umask(0022);

        try {
            $created = mkdir($directory, 0700);
            self::assertTrue($created);
            self::assertTrue(mkdir($directory.'/logs', 0700));
            self::assertSame(0022, umask());
            $project = dirname(__DIR__, 2);
            $app = require $project.'/bootstrap/app.php';
            self::assertInstanceOf(Application::class, $app);
            $app->useStoragePath($directory);
            $logging = require $project.'/config/logging.php';
            if ($emergency) {
                $logging['channels'][$channel] = ['driver' => 'synthetic-invalid-driver'];
                $logging['channels']['emergency']['permission'] = 0644;
            } else {
                $logging['channels'][$channel]['name'] = 'synthetic-file-permission';
            }
            $app->instance('config', new Repository(['logging' => $logging]));
            $manager = $app->make('log');
            self::assertInstanceOf(LogManager::class, $manager);
            self::assertSame($manager, $app->make('log'));
            $logger = $manager->channel($channel);
            self::assertInstanceOf(Logger::class, $logger);
            $monolog = $logger->getLogger();
            self::assertInstanceOf(Monolog::class, $monolog);
            self::assertSame($emergency ? 'laravel' : 'synthetic-file-permission', $monolog->getName());
            $handlers = $monolog->getHandlers();
            self::assertCount(1, $handlers);
            if (! $emergency && $channel !== 'single') {
                self::assertInstanceOf(RotatingFileHandler::class, $handlers[0]);
            } else {
                self::assertInstanceOf(StreamHandler::class, $handlers[0]);
            }
            $logger->emergency('Synthetic private log permission regression.', ['authorization' => 'Bearer synthetic-permission-secret']);
            $monolog->close();
            $files = glob($directory.'/logs/*.log');
            self::assertIsArray($files);
            self::assertCount(1, $files);
            $file = $files[0];
            self::assertFileExists($file);
            self::assertFalse(is_link($file));
            self::assertSame(realpath($directory.'/logs'), realpath(dirname($file)));
            clearstatcache(true, $file);
            $permissions = fileperms($file);
            self::assertIsInt($permissions);
            self::assertSame(0600, $permissions & 0777);
            self::assertInstanceOf(PrivateLogManager::class, $manager);
            $contents = file_get_contents($file);
            self::assertIsString($contents);
            self::assertStringContainsString('Synthetic private log permission regression.', $contents);
            self::assertStringNotContainsString('synthetic-permission-secret', $contents);
            self::assertStringContainsString('[redacted]', $contents);
            if ($emergency) {
                self::assertStringContainsString('Unable to create configured logger. Using emergency logger.', $contents);
            } else {
                self::assertSame(0600, $logging['channels'][$channel]['permission']);
            }
        } finally {
            umask($previousUmask);
            try {
                if ($logger instanceof Logger && $logger->getLogger() instanceof Monolog) {
                    $logger->getLogger()->close();
                }
            } finally {
                Container::setInstance($previousContainer);
                if ($created) {
                    foreach (glob($directory.'/logs/*.log') ?: [] as $file) {
                        if (is_file($file) && ! is_link($file)) {
                            unlink($file);
                        }
                    }
                    if (is_dir($directory.'/logs')) {
                        rmdir($directory.'/logs');
                    }
                    rmdir($directory);
                }
            }
        }
    }
}
