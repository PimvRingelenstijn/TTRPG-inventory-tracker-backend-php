<?php

namespace App\DTOs;

class LoginRequest
{
    public function __construct(
        public readonly string $email,
        public readonly string $password,
    ) {}
}
