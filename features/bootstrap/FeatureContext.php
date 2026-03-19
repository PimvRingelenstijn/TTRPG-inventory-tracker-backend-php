<?php

putenv('APP_ENV=testing');
error_reporting(E_ALL & ~E_DEPRECATED & ~E_USER_DEPRECATED);

use Behat\Behat\Context\Context;
use Behat\Gherkin\Node\PyStringNode;
use Illuminate\Contracts\Http\Kernel as HttpKernel;
use Illuminate\Http\Request;


final class FeatureContext implements Context
{
    // ============ 1. PROPERTIES ============
    private string $basePath;
    private mixed $app = null;
    private ?int $lastResponseStatus = null;
    private mixed $lastResponseJson = null;

    /** @var array<string, mixed> */
    private array $storedValues = [];

    /** @var array<string, mixed> */
    private array $lastResponseCookies = [];

    // ============ 2. CONSTRUCTOR ============
    public function __construct()
    {
        $this->basePath = dirname(__DIR__, 2);
    }

    // ============ 3. HOOKS ============
    /**
     * @BeforeScenario
     */
    public function bootstrapLaravel(): void
    {
        $this->app = require $this->basePath . '/bootstrap/app.php';

        // Bootstrap first so AppServiceProvider registers the real Service
        $this->app->make(HttpKernel::class)->bootstrap();
        $this->app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

        // Now replace with mock for Behat (production code stays clean, test setup does the swap)
        $this->app->forgetInstance(\PHPSupabase\Service::class);
        $this->app->offsetUnset(\PHPSupabase\Service::class);
        $this->app->singleton(\PHPSupabase\Service::class, fn () => new \Mocks\MockSupabaseService());

        $this->app->forgetInstance(\App\Services\AuthService::class);
        $this->app->bind(\App\Services\AuthService::class, function ($app) {
            return new \App\Services\AuthService(
                $app->make(\PHPSupabase\Service::class),
                $app->make(\App\Repositories\UserRepository::class)
            );
        });

        \Illuminate\Support\Facades\Artisan::call('migrate', ['--force' => true]);

        $this->storedValues = [];
        $this->lastResponseStatus = null;
        $this->lastResponseJson = null;
        $this->lastResponseCookies = [];
    }

    // ============ 4. GIVEN STEPS (Setup) ============
    /**
     * @Given a game system exists with name :name and description :description
     */
    public function gameSystemExists(string $name, string $description): void
    {
        // Create the game system via API
        $this->sendRequestWithBody('POST', '/game-systems', new PyStringNode([
            json_encode([
                'name' => $name,
                'description' => $description
            ])
        ], 0));

        // Verify it was created successfully
        $this->assertResponseStatus(201);

        // Store the ID for later use
        $this->storeResponseValue('id', 'game_system_id');
    }

    /**
     * @Given there are :count game systems in the system
     */
    public function thereAreGameSystems(int $count): void
    {
        // Create multiple game systems
        for ($i = 1; $i <= $count; $i++) {
            $this->sendRequestWithBody('POST', '/game-systems', new PyStringNode([
                json_encode([
                    'name' => "Game System {$i}",
                    'description' => "Description for game system {$i}"
                ])
            ], 0));

            $this->assertResponseStatus(201);
        }
    }

    /**
     * @Given a registered user exists with email :email, username :username and password :password
     */
    public function registeredUserExists(string $email, string $username, string $password): void
    {
        // Register the user via the actual registration endpoint
        $this->sendRequestWithBody('POST', '/auth/register', new PyStringNode([
            json_encode([
                'email' => $email,
                'username' => $username,
                'password' => $password
            ])
        ], 0));

        // Verify registration was successful
        $this->assertResponseStatus(201);
    }

    // ============ 5. WHEN STEPS (Actions) ============
    /**
     * @When /^I send a (GET|POST|PUT|PATCH|DELETE) request to ([^ ]+)$/
     */
    public function sendRequest(string $method, string $uri): void
    {
        $uri = $this->expandUri(trim($uri));
        $this->performRequest($method, $uri, []);
    }

    /**
     * @When /^I send a (POST|PUT|PATCH) request to ([^ ]+) with body:$/
     */
    public function sendRequestWithBody(string $method, string $uri, PyStringNode $body): void
    {
        $uri = $this->expandUri(trim($uri));
        $data = json_decode($body->getRaw(), true, 512, JSON_THROW_ON_ERROR);
        $this->performRequest($method, $uri, $data);
    }

    // ============ 6. THEN STEPS (Assertions) ============
    /**
     * @Then the response status should be :status
     */
    public function assertResponseStatus(int $status): void
    {
        if ($this->lastResponseStatus !== $status) {
            throw new \RuntimeException(
                "Expected status {$status}, got {$this->lastResponseStatus}. Response: " .
                json_encode($this->lastResponseJson, JSON_PRETTY_PRINT)
            );
        }
    }

    /**
     * @Then the response should contain key :key
     */
    public function assertResponseHasKey(string $key): void
    {
        $key = trim($key, '"');
        if (!\Illuminate\Support\Arr::has($this->lastResponseJson, $key)) {
            throw new \RuntimeException(
                "Expected response to contain key '{$key}'. Response: " .
                json_encode($this->lastResponseJson, JSON_PRETTY_PRINT)
            );
        }
    }

    /**
     * @Then the response should contain :key with value :value
     */
    public function assertResponseContains(string $key, string $value): void
    {
        $actual = \Illuminate\Support\Arr::get($this->lastResponseJson, $key);
        if ((string) $actual !== $value) {
            throw new \RuntimeException(
                "Expected response key '{$key}' to be '{$value}', got " .
                json_encode($actual) . '. Response: ' . json_encode($this->lastResponseJson, JSON_PRETTY_PRINT)
            );
        }
    }

    /**
     * @Then the response should be a JSON array
     */
    public function assertResponseIsJsonArray(): void
    {
        if (!is_array($this->lastResponseJson)) {
            throw new \RuntimeException(
                'Expected response to be a JSON array, got ' . gettype($this->lastResponseJson)
            );
        }
        if (!array_is_list($this->lastResponseJson)) {
            throw new \RuntimeException('Expected response to be a JSON list (array with numeric keys).');
        }
    }

    /**
     * @Then the response should be a JSON array with :count items
     */
    public function assertResponseIsJsonArrayWithCount(int $count): void
    {
        // First verify it's a valid JSON array
        $this->assertResponseIsJsonArray();

        // Then verify the count
        if (count($this->lastResponseJson) !== $count) {
            throw new \RuntimeException(
                "Expected JSON array with {$count} items, got " . count($this->lastResponseJson) .
                ". Response: " . json_encode($this->lastResponseJson, JSON_PRETTY_PRINT)
            );
        }
    }

    // ============ THEN STEPS ============
    /**
     * @Then I should receive an access token cookie
     */
    public function assertAccessTokenCookieReceived(): void
    {
        if (!isset($this->lastResponseCookies['access_token'])) {
            throw new \RuntimeException(
                "Expected access_token cookie in response, but none found. Cookies: " .
                json_encode($this->lastResponseCookies)
            );
        }

        // Optional: Verify it's not empty
        if (empty($this->lastResponseCookies['access_token'])) {
            throw new \RuntimeException("Access token cookie was empty");
        }
    }

    // ============ 7. HELPER STEPS (Utilities) ============
    /**
     * @Then I store the value of :path from the response as :variable
     */
    public function storeResponseValue(string $path, string $variable): void
    {
        $path = trim($path, '"');
        $variable = trim($variable, '"');
        $value = \Illuminate\Support\Arr::get($this->lastResponseJson, $path);

        if ($value === null && !\Illuminate\Support\Arr::has($this->lastResponseJson, $path)) {
            throw new \RuntimeException("Could not find path '{$path}' in response.");
        }

        $this->storedValues[$variable] = $value;
    }

    // ============ 8. PRIVATE HELPERS ============
//    private function performRequest(string $method, string $uri, array $data): void
//    {
//        $method = strtoupper($method);
//        $server = [
//            'HTTP_CONTENT_TYPE' => 'application/json',
//            'HTTP_ACCEPT' => 'application/json',
//        ];
//
//        $parameters = [];
//        $content = null;
//
//        if (!empty($data) && in_array($method, ['POST', 'PUT', 'PATCH'])) {
//            $parameters = $data;
//            $content = json_encode($data);
//        }
//
//        $request = Request::create($uri, $method, $parameters, [], [], $server, $content);
//        $request->headers->set('Content-Type', 'application/json');
//
//        $kernel = $this->app->make(HttpKernel::class);
//        $response = $kernel->handle($request);
//        $kernel->terminate($request, $response);
//
//        $this->lastResponseStatus = $response->getStatusCode();
//        $this->lastResponseJson = json_decode($response->getContent(), true);
//
//        $this->lastResponseCookies = [];
//        foreach ($response->headers->getCookies() as $cookie) {
//            $this->lastResponseCookies[$cookie->getName()] = $cookie->getValue();
//        }
//    }

    private function performRequest(string $method, string $uri, array $data): void
    {
        $method = strtoupper($method);
        $server = [
            'HTTP_CONTENT_TYPE' => 'application/json',
            'HTTP_ACCEPT' => 'application/json',
        ];

        $parameters = [];
        $content = null;

        if (!empty($data) && in_array($method, ['POST', 'PUT', 'PATCH'])) {
            $parameters = $data;
            $content = json_encode($data);
        }

        $request = Request::create($uri, $method, $parameters, [], [], $server, $content);
        $request->headers->set('Content-Type', 'application/json');

        $kernel = $this->app->make(HttpKernel::class);

        try {
            $response = $kernel->handle($request);
            $kernel->terminate($request, $response);

            $this->lastResponseStatus = $response->getStatusCode();
            $this->lastResponseJson = json_decode($response->getContent(), true);

            // Capture cookies from the response
            $this->lastResponseCookies = [];
            foreach ($response->headers->getCookies() as $cookie) {
                $this->lastResponseCookies[$cookie->getName()] = $cookie->getValue();
            }
        } catch (\Exception $e) {
            $this->lastResponseStatus = 500;
            $this->lastResponseJson = ['message' => 'Server Error', 'error' => $e->getMessage()];
        }
    }

    private function expandUri(string $uri): string
    {
        // Check for placeholders and replace
        if (str_contains($uri, '{')) {
            foreach ($this->storedValues as $key => $value) {
                $uri = str_replace('{' . $key . '}', (string)$value, $uri);
            }
        }
        return $uri;
    }
}
