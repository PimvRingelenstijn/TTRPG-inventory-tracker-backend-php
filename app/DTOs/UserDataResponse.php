<?php

namespace App\DTOs;

use Carbon\Carbon;

class UserDataResponse
{
    public function __construct(
        public readonly string $uuid,
        public readonly string $email,
        public readonly string $username,
        public readonly Carbon $created_at,
    ) {}

    public function toArray(): array
    {
        return [
            'uuid' => $this->uuid,
            'email' => $this->email,
            'username' => $this->username,
            'created_at' => $this->created_at->toIso8601String(),
        ];
    }
}
