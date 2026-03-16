<?php

namespace App\Mappers;

use App\DTOs\RegistrationRequest;

class UserMapper
{
    /**
     * Map RegistrationRequest + Supabase auth response to User model attributes.
     */
    public static function toUser(RegistrationRequest $request, object $authResponse): array
    {
        $authUser = $authResponse->user ?? $authResponse;
        $userId = $authUser->id ?? null;
        if (!$userId) {
            throw new \InvalidArgumentException('Auth response missing user id');
        }

        return [
            'uuid' => $userId,
            'username' => $request->username,
        ];
    }
}
