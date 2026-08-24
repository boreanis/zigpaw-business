<?php

namespace Tests\Feature;

use Tests\TestCase;

class BusinessHardeningTest extends TestCase
{
    public function test_the_business_bff_owns_no_application_database(): void
    {
        self::assertNull(config('database.default'));
        self::assertNotEmpty(config('database.connections'));
        self::assertSame([], array_filter(config('database.connections')));
        self::assertSame('array', config('cache.default'));
        self::assertSame('sync', config('queue.default'));
        self::assertFileDoesNotExist(base_path('database/seeders/DatabaseSeeder.php'));
        self::assertEmpty(glob(base_path('database/migrations/*.php')));
    }

    public function test_database_commands_fail_closed(): void
    {
        $this->artisan('migrate')->assertExitCode(1);
        $this->artisan('db:seed')->assertExitCode(1);
        $this->artisan('db:wipe')->assertExitCode(1);
    }
}
