<?php

namespace App\DTOs;

use Carbon\Carbon;

readonly class UserDataResponse
{
    public function __construct(
        public string $uuid,
        public string $email,
        public string $username,
        public Carbon $created_at,
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
