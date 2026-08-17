<?php

namespace App\Exceptions;

use RuntimeException;

class PlatformApiException extends RuntimeException
{
    /** @param array<string, list<string>> $errors */
    public function __construct(
        public readonly int $status,
        string $message,
        public readonly array $errors = [],
    ) {
        parent::__construct($message, $status);
    }
}
