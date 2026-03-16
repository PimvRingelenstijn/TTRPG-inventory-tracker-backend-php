<?php

namespace App\DTOs;

use Carbon\Carbon;

class LoginResult
{
    public function __construct(
        public readonly string $access_token,
        public readonly Carbon $expires,
        public readonly UserDataResponse $user_info,
    ) {}
}
