<?php

namespace App\Support;

use Illuminate\Log\Logger;
use Monolog\LogRecord;

final class RedactTeamInvitationSecrets
{
    public function __invoke(Logger $logger): void
    {
        $logger->pushProcessor(fn (LogRecord $record): LogRecord => $record->with(
            message: $this->text($record->message), context: $this->context($record->context),
        ));
    }

    /** @param array<string|int, mixed> $context @return array<string|int, mixed> */
    private function context(array $context): array
    {
        foreach ($context as $key => $value) {
            $normalized = str_replace('-', '_', strtolower((string) $key));
            if (! str_ends_with($normalized, '_hash') && preg_match('/(^|_)(token|secret|authorization|cookie)(_|$)/', $normalized) === 1) {
                $context[$key] = '[redacted]';
            } elseif (is_array($value)) {
                $context[$key] = $this->context($value);
            } elseif (is_string($value)) {
                $context[$key] = $this->text($value);
            }
        }

        return $context;
    }

    private function text(string $value): string
    {
        $value = preg_replace('/([?&](?:token|invitation_token)=)[^&#\s]+/i', '$1[redacted]', $value) ?? $value;

        return preg_replace('/(X-Zigpaw-Team-Invitation-Token\s*:\s*)[^\s,;]+/i', '$1[redacted]', $value) ?? $value;
    }
}
