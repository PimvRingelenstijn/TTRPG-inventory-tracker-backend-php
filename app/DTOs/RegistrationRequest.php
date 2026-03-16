<?php

namespace App\DTOs;

class RegistrationRequest
{
    public function __construct(
        public readonly string $email,
        public readonly string $password,
        public readonly string $username,
    ) {}
}
