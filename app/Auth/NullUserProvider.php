<?php

namespace App\Auth;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Contracts\Auth\UserProvider;

class NullUserProvider implements UserProvider
{
    public function retrieveById(mixed $identifier): ?Authenticatable
    {
        return null;
    }

    public function retrieveByToken(mixed $identifier, #[\SensitiveParameter] $token): ?Authenticatable
    {
        return null;
    }

    public function updateRememberToken(Authenticatable $user, #[\SensitiveParameter] $token): void {}

    public function retrieveByCredentials(#[\SensitiveParameter] array $credentials): ?Authenticatable
    {
        return null;
    }

    public function validateCredentials(Authenticatable $user, #[\SensitiveParameter] array $credentials): bool
    {
        return false;
    }

    public function rehashPasswordIfRequired(Authenticatable $user, #[\SensitiveParameter] array $credentials, bool $force = false): void {}
}
