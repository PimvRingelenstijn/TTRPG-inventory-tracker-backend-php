<?php

namespace Mocks;

use PHPSupabase\Service;

class MockSupabaseService extends Service
{
    private ?MockSupabaseAuth $auth = null;

    public function __construct(?string $key = null, ?string $url = null)
    {
        echo "MockSupabaseService constructor called with key: $key, url: $url\n";
    }

    public function createAuth(): MockSupabaseAuth
    {
        echo "MockSupabaseService::createAuth() called\n";
        if (!$this->auth) {
            echo "Creating new MockSupabaseAuth instance\n";
            $this->auth = new MockSupabaseAuth();
        }
        echo "Returning: " . get_class($this->auth) . "\n";
        return $this->auth;
    }
}
