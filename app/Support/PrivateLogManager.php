<?php

declare(strict_types=1);

namespace App\Support;

use Illuminate\Log\Logger;
use Illuminate\Log\LogManager;
use Monolog\Handler\StreamHandler;
use Monolog\Level;
use Monolog\Logger as Monolog;
use Psr\Log\LoggerInterface;

class PrivateLogManager extends LogManager
{
    protected function createEmergencyLogger(): LoggerInterface
    {
        $config = $this->configurationFor('emergency');
        $handler = new StreamHandler(
            $config['path'] ?? $this->app->storagePath().'/logs/laravel.log',
            Level::Debug,
            true,
            0600,
        );
        $logger = new Logger(
            new Monolog('laravel', $this->prepareHandlers([$handler])),
            $this->app['events'],
        );
        (new RedactTeamInvitationSecrets)($logger);

        return $logger;
    }
}
