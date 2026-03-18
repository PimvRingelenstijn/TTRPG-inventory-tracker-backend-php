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
        echo ">>> MockSupabaseAuth::createUserWithEmailAndPassword\n";
        echo "    Email: $email\n";

        $userId = 'user_' . uniqid();
        $this->users[$email] = [
            'id' => $userId,
            'email' => $email,
            'password' => password_hash($password, PASSWORD_DEFAULT),
            'username' => $data['username'] ?? null
        ];

        // Store the response in the format the mapper expects
        // The real PHPSupabase\Auth returns the user object directly
        $this->lastResponse = (object)[
            'user' => (object)[
                'id' => $userId,
                'email' => $email
            ]
        ];

        echo "    User created with ID: $userId\n";
        echo "    Response stored: " . json_encode($this->lastResponse) . "\n";
    }

    public function signInWithEmailAndPassword(string $email, string $password): void
    {
        echo ">>> MockSupabaseAuth::signInWithEmailAndPassword\n";

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
        echo ">>> MockSupabaseAuth::data() called\n";
        if (!$this->lastResponse) {
            throw new \Exception('No data available');
        }

        // The real PHPSupabase\Auth returns the data object directly
        // So if we stored it with a 'data' wrapper, unwrap it
        if (isset($this->lastResponse->data)) {
            $result = $this->lastResponse->data;
            echo "    Returning (unwrapped): " . json_encode($result) . "\n";
            return $result;
        }

        echo "    Returning: " . json_encode($this->lastResponse) . "\n";
        return $this->lastResponse;
    }

    public function getUser(string $accessToken): object
    {
        echo ">>> MockSupabaseAuth::getUser\n";

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
