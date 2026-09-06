<?php

namespace App\Exceptions;

use RuntimeException;

class PlatformApiException extends RuntimeException
{
    /**
     * @param  array<string, list<string>>  $errors
     */
    public function __construct(
        public readonly int $status,
        string $message,
        public readonly array $errors = [],
        public readonly ?string $requestId = null,
        public readonly ?string $errorCode = null,
    ) {
        parent::__construct($message, $status);
    }
}
