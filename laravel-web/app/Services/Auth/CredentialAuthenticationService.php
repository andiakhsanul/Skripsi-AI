<?php

namespace App\Services\Auth;

use App\Models\User;
use Illuminate\Support\Facades\Hash;

class CredentialAuthenticationService
{
    public function attempt(string $email, string $password, ?string $expectedRole = null): ?User
    {
        $normalizedEmail = str($email)->trim()->lower()->value();
        $user = User::query()->where('email', $normalizedEmail)->first();

        if (! $user || ! Hash::check($password, $user->password)) {
            return null;
        }

        if ($expectedRole !== null && $user->role !== $expectedRole) {
            return null;
        }

        return $user;
    }
}
