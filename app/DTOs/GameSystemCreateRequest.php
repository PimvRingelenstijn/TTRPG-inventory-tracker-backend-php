<?php

namespace App\DTOs;

class GameSystemCreateRequest
{
    public function __construct(
        public readonly string $name,
        public readonly string $description,
    ) {}
}
