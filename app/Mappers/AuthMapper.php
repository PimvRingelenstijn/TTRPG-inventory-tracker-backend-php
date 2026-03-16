<?php

namespace App\Mappers;

use App\DTOs\LoginResult;
use App\DTOs\UserDataResponse;
use App\Models\User;
use Carbon\Carbon;

class AuthMapper
{
    /**
     * Map auth response + user data to LoginResult.
     * PHPSupabase auth->data() returns object with access_token, expires_at, user.
     */
    public static function toLoginResult(object $authData, UserDataResponse $userData): LoginResult
    {
        $expiresAt = isset($authData->expires_at)
            ? Carbon::createFromTimestamp($authData->expires_at)
            : (isset($authData->expires_in)
                ? Carbon::now()->addSeconds($authData->expires_in)
                : Carbon::now()->addDays(7));

        return new LoginResult(
            access_token: $authData->access_token,
            expires: $expiresAt,
            user_info: $userData,
        );
    }

    /**
     * Map Supabase user + DB User to UserDataResponse.
     */
    public static function toUserDataResponse(object $authUser, User $dbUser): UserDataResponse
    {
        return new UserDataResponse(
            uuid: $authUser->id,
            email: $authUser->email ?? '',
            username: $dbUser->username,
            created_at: Carbon::parse($dbUser->created_at),
        );
    }
}
