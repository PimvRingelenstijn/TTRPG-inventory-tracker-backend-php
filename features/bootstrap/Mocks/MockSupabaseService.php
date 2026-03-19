<?php

namespace Mocks;

use PHPSupabase\Service;

class MockSupabaseService extends Service
{
    private ?MockSupabaseAuth $auth = null;

    public function __construct(?string $key = null, ?string $url = null)
    {
    }

    public function createAuth(): MockSupabaseAuth
    {
        if (!$this->auth) {
            $this->auth = new MockSupabaseAuth();
        }
        return $this->auth;
    }
}
