<?php

namespace App\Utils;

use Carbon\Carbon;
use Illuminate\Http\Response;

class CookieUtils
{
    public static function setAccessTokenCookie(Response $response, string $accessToken, Carbon $expires): void
    {
        $minutes = (int) max(1, Carbon::now()->diffInMinutes($expires));
        $response->cookie(
            'access_token',
            $accessToken,
            $minutes,
            '/',
            null,
            false,
            true,
            false,
            'lax'
        );
    }
}
