<?php

putenv('APP_ENV=testing');
error_reporting(E_ALL & ~E_DEPRECATED & ~E_USER_DEPRECATED);

use Behat\Behat\Context\Context;
use Behat\Gherkin\Node\PyStringNode;
use Behat\Gherkin\Node\TableNode;
use Illuminate\Contracts\Http\Kernel as HttpKernel;
use Illuminate\Http\Request;

final class FeatureContext implements Context
{
    private string $basePath;

    private mixed $app = null;

    private ?int $lastResponseStatus = null;

    private mixed $lastResponseJson = null;

    /** @var array<string, mixed> */
    private array $storedValues = [];

    public function __construct()
    {
        $this->basePath = dirname(__DIR__, 2);
    }

    /**
     * @BeforeScenario
     */
    public function bootstrapLaravel(): void
    {
        $this->app = require $this->basePath . '/bootstrap/app.php';
        $this->app->make(HttpKernel::class)->bootstrap();
        $this->app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();
        \Illuminate\Support\Facades\Artisan::call('migrate', ['--force' => true]);
    }

    /**
     * @When /^I send a (POST|PUT|PATCH) request to ([^ ]+) with body:$/
     */
    public function iSendARequestToWithBody(string $method, string $uri, PyStringNode $body): void
    {
        $uri = $this->expandUri(trim($uri));
        $data = json_decode($body->getRaw(), true, 512, JSON_THROW_ON_ERROR);
        $this->performRequest($method, $uri, $data);
    }

    /**
     * @When /^I send a (GET|POST|PUT|PATCH|DELETE) request to ([^ ]+)$/
     */
    public function iSendARequestTo(string $method, string $uri): void
    {
        $uri = $this->expandUri(trim($uri));
        $this->performRequest($method, $uri, []);
    }

    /**
     * @Then the response status should be :status
     */
    public function theResponseStatusShouldBe(int $status): void
    {
        if ($this->lastResponseStatus !== $status) {
            throw new \RuntimeException(
                "Expected status {$status}, got {$this->lastResponseStatus}. Response: " .
                json_encode($this->lastResponseJson, JSON_PRETTY_PRINT)
            );
        }
    }

    /**
     * @Then the response should contain :key with value :value
     */
    public function theResponseShouldContainWithValue(string $key, string $value): void
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
     * @Then the response should contain key :key
     */
    public function theResponseShouldContainKey(string $key): void
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
     * @Then I store the value of :path from the response as :variable
     */
    public function iStoreTheValueFromResponseAs(string $path, string $variable): void
    {
        $path = trim($path, '"');
        $variable = trim($variable, '"');
        $value = \Illuminate\Support\Arr::get($this->lastResponseJson, $path);
        if ($value === null && !\Illuminate\Support\Arr::has($this->lastResponseJson, $path)) {
            throw new \RuntimeException("Could not find path '{$path}' in response.");
        }
        $this->storedValues[$variable] = $value;
    }

    /**
     * @Then the response should be a JSON array
     */
    public function theResponseShouldBeAJsonArray(): void
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
        $response = $kernel->handle($request);
        $kernel->terminate($request, $response);

        $this->lastResponseStatus = $response->getStatusCode();
        $this->lastResponseJson = json_decode($response->getContent(), true);
    }

    private function expandUri(string $uri): string
    {
        foreach ($this->storedValues as $key => $value) {
            $uri = str_replace('{' . $key . '}', (string) $value, $uri);
        }
        return $uri;
    }
}
