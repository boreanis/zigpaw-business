<?php

namespace Tests\Feature;

use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class DatabaseFreeSessionBoundaryTest extends TestCase
{
    #[Test]
    public function stale_database_backends_cannot_reintroduce_sql_state(): void
    {
        $keys = ['SESSION_DRIVER', 'CACHE_STORE', 'QUEUE_CONNECTION'];
        $previous = collect($keys)->mapWithKeys(static fn (string $key): array => [$key => getenv($key)])->all();

        try {
            foreach ($keys as $key) {
                putenv("{$key}=database");
                $_ENV[$key] = 'database';
                $_SERVER[$key] = 'database';
            }

            $session = require config_path('session.php');
            $cache = require config_path('cache.php');
            $queue = require config_path('queue.php');

            $this->assertSame('redis', $session['driver']);
            $this->assertSame('redis', $cache['default']);
            $this->assertSame('redis', $queue['default']);
            $this->assertNull(config('database.default'));
        } finally {
            foreach ($previous as $key => $value) {
                if ($value === false) {
                    putenv($key);
                    unset($_ENV[$key], $_SERVER[$key]);
                } else {
                    putenv("{$key}={$value}");
                    $_ENV[$key] = $value;
                    $_SERVER[$key] = $value;
                }
            }
        }
    }
}
