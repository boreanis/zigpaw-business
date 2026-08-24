<?php

use Illuminate\Support\Facades\Artisan;

// This BFF deliberately owns no database. Keep framework commands visible but
// fail closed before they can resolve a connection or execute a seeder.
foreach ([
    'db:seed',
    'db:wipe',
    'migrate',
    'migrate:fresh',
    'migrate:install',
    'migrate:refresh',
    'migrate:reset',
    'migrate:rollback',
    'migrate:status',
] as $command) {
    Artisan::command($command, function () use ($command): int {
        $this->error("{$command} is disabled: zigpaw-business owns no database.");

        return self::FAILURE;
    })->purpose('Disabled in the database-free Business BFF');
}
