<?php

namespace Mocks;

use PHPSupabase\Auth;

class MockSupabaseAuth extends Auth
{
    private array $users = [];
    private ?object $lastResponse = null;

    public function __construct()
    {
        // Skip parent constructor
    }

    /**
     * Must match parent signature exactly:
     * public function createUserWithEmailAndPassword(string $email, string $password, array $data = []): void
     */
    public function createUserWithEmailAndPassword(string $email, string $password, array $data = []): void
    {
        $userId = 'user_' . uniqid();
        $this->users[$email] = [
            'id' => $userId,
            'email' => $email,
            'password' => password_hash($password, PASSWORD_DEFAULT),
            'username' => $data['username'] ?? null
        ];

        $this->lastResponse = (object)[
            'user' => (object)[
                'id' => $userId,
                'email' => $email
            ]
        ];
    }

    public function signInWithEmailAndPassword(string $email, string $password): void
    {
        if (!isset($this->users[$email])) {
            throw new \Exception('Invalid credentials');
        }

        $user = $this->users[$email];

        if (!password_verify($password, $user['password'])) {
            throw new \Exception('Invalid credentials');
        }

        $this->lastResponse = (object)[
            'data' => (object)[
                'access_token' => 'mock_token_' . $user['id'],
                'expires_in' => 3600,
                'user' => (object)[
                    'id' => $user['id'],
                    'email' => $user['email']
                ]
            ]
        ];
    }

    public function data(): object
    {
        if (!$this->lastResponse) {
            throw new \Exception('No data available');
        }

        if (isset($this->lastResponse->data)) {
            return $this->lastResponse->data;
        }

        return $this->lastResponse;
    }

    public function getUser(string $accessToken): object
    {
        if (strpos($accessToken, 'mock_token_') === 0) {
            $userId = str_replace('mock_token_', '', $accessToken);

            foreach ($this->users as $user) {
                if ($user['id'] === $userId) {
                    return (object)[
                        'id' => $userId,
                        'aud' => 'authenticated',
                        'email' => $user['email']
                    ];
                }
            }
        }

        throw new \Exception('Invalid token');
    }

    public function clearUsers(): void
    {
        $this->users = [];
        $this->lastResponse = null;
    }
}
