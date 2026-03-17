# Behat Setup voor Laravel API - Gedetailleerde Uitleg

## Overzicht
Deze update voegt Behat toe aan het Laravel project voor Behavior-Driven Development (BDD) testing van de API endpoints. Behat maakt het mogelijk om tests te schrijven in natuurlijke taal (Gherkin syntax) die zowel voor developers als niet-technische stakeholders leesbaar zijn.

## Wat is er toegevoegd?

### 1. Behat Package (`composer.json`)
```bash
composer require --dev behat/behat
```
Dit installeert het Behat framework in de `require-dev` sectie van composer.json, wat betekent dat het alleen beschikbaar is tijdens development en testing, niet in productie.

### 2. Configuratiebestand (`behat.yml`)
```yaml
default:
    suites:
        default:
            paths: ["features"]
            contexts:
                - FeatureContext
```

**Wat doet dit?**
- `default`: Dit is het standaard profiel. Je kunt meerdere profielen maken (bijv. `staging`, `production`)
- `suites`: Een suite is een verzameling features. Hier hebben we één suite genaamd "default"
- `paths`: Vertelt Behat waar de `.feature` bestanden staan
- `contexts`: De FeatureContext class bevat alle step definitions (de PHP code die de Gherkin steps uitvoert)

### 3. Test Omgeving Configuratie (`.env.testing`)
```env
APP_ENV=testing
DB_CONNECTION=sqlite
DB_DATABASE=:memory:
CACHE_STORE=array
SESSION_DRIVER=array
QUEUE_CONNECTION=sync
```

**Waarom een aparte .env?**
- Laravel laadt automatisch `.env.testing` wanneer `APP_ENV=testing` is ingesteld
- Gebruikt een in-memory SQLite database, wat betekent:
  - Snelle tests (alles in RAM)
  - Geen impact op de productie/development database
  - Database wordt na elke test automatisch weggegooid
  - Geen cleanup nodig

### 4. Feature Context (`features/bootstrap/FeatureContext.php`)

Dit is het **hart** van de Behat setup. Hier wordt alles aan elkaar geknoopt:

#### A. Initialisatie
```php
public function __construct()
{
    $this->basePath = dirname(__DIR__, 2);
}
```
Slaat het project root pad op voor later gebruik.

#### B. BeforeScenario Hook
```php
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
```

**Wat gebeurt hier?**
1. Voor **elk scenario** wordt Laravel opnieuw opgestart (fresh start)
2. `bootstrap/app.php` wordt geladen - dit is hetzelfde bestand dat Laravel normaal gebruikt
3. De HTTP Kernel wordt gebootstrap (laadt middleware, routes, etc.)
4. De Console Kernel wordt gebootstrap (voor Artisan commando's)
5. Migrations worden uitgevoerd op de in-memory database
   - Dit creëert alle tabellen (`users`, `game_systems`, etc.)
   - Gebeurt in milliseconden omdat het in-memory is
   - Elke scenario begint met een schone database

#### C. Step Definitions (De Magische Koppeling)

**Hoe werkt de koppeling tussen Gherkin en PHP?**

Wanneer je in een `.feature` file schrijft:
```gherkin
When I send a POST request to /game-systems with body:
  """
  {
    "name": "Dungeons & Dragons 5e",
    "description": "The fifth edition"
  }
  """
```

Dan zoekt Behat naar een method met een `@When` annotation die **matcht met deze tekst**:

```php
/**
 * @When /^I send a (POST|PUT|PATCH) request to ([^ ]+) with body:$/
 */
public function iSendARequestToWithBody(string $method, string $uri, PyStringNode $body)
{
    // Deze method wordt uitgevoerd!
}
```

**De matching werkt zo:**
1. `@When` vertelt Behat: "deze method hoort bij When steps"
2. De regex pattern `/^I send a (POST|PUT|PATCH) request to ([^ ]+) with body:$/` definieert:
   - `(POST|PUT|PATCH)` = capture group 1 → wordt de `$method` parameter
   - `([^ ]+)` = capture group 2 (alles behalve spaties) → wordt de `$uri` parameter
   - `with body:$` = de tekst moet eindigen met "with body:"
   - De `"""..."""` content (PyString) wordt automatisch als `$body` parameter meegegeven

3. Behat voert de method uit met de gevangen waarden:
   ```php
   iSendARequestToWithBody("POST", "/game-systems", PyStringNode{...json...})
   ```

**Voorbeeld van parameter extractie:**
```gherkin
Then the response status should be 201
```
Matcht met:
```php
/**
 * @Then the response status should be :status
 */
public function theResponseStatusShouldBe(int $status)
{
    // $status = 201
}
```
De `:status` token is een placeholder die alles matcht en het als parameter doorgeeft.

**Voorbeeld met regex:**
```gherkin
When I send a GET request to /game-systems/5
```
Matcht met:
```php
/**
 * @When /^I send a (GET|POST|PUT|PATCH|DELETE) request to ([^ ]+)$/
 */
public function iSendARequestTo(string $method, string $uri)
{
    // $method = "GET"
    // $uri = "/game-systems/5"
}
```

#### D. Request Uitvoering
```php
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
```

**Stap voor stap:**
1. **Request aanmaken**: `Request::create()` maakt een Symfony Request object
   - Net zoals een echte HTTP request, maar zonder netwerk
   - Bevat URI, method (GET/POST/etc), headers, body
   - Laravel krijgt exact dezelfde request als van een browser

2. **Request verwerken**: `$kernel->handle($request)`
   - De request gaat door **alle middleware** (net als normaal)
   - Routes worden gematcht
   - Controller wordt aangeroepen
   - Validatie gebeurt
   - Database queries worden uitgevoerd
   - Response wordt gegenereerd
   - Dit is **exact** hetzelfde proces als een echte API call

3. **Response opslaan**: De status code en JSON body worden opgeslagen in class properties
   - `$this->lastResponseStatus` = HTTP status code (200, 201, 422, etc.)
   - `$this->lastResponseJson` = De response body als PHP array

4. **Cleanup**: `$kernel->terminate()` ruimt op (sluit database connecties, etc.)

#### E. Variable Substitutie (Dynamic Values)
```php
private array $storedValues = [];

public function iStoreTheValueFromResponseAs(string $path, string $variable): void
{
    $value = \Illuminate\Support\Arr::get($this->lastResponseJson, $path);
    $this->storedValues[$variable] = $value;
}

private function expandUri(string $uri): string
{
    foreach ($this->storedValues as $key => $value) {
        $uri = str_replace('{' . $key . '}', (string) $value, $uri);
    }
    return $uri;
}
```

**Hoe werkt dit?**

In de feature file:
```gherkin
When I send a POST request to /game-systems with body:
  """
  {"name": "D&D 5e", "description": "Cool game"}
  """
And I store the value of "id" from the response as "game_system_id"
When I send a GET request to /game-systems/{game_system_id}
```

**Stap 1**: POST request creëert een game system
- Response: `{"id": 123, "name": "D&D 5e", "description": "Cool game"}`

**Stap 2**: "I store the value of "id"..."
- `Arr::get($this->lastResponseJson, "id")` haalt `123` uit de response
- Opgeslagen in `$this->storedValues["game_system_id"] = 123`

**Stap 3**: "I send a GET request to /game-systems/{game_system_id}"
- Voordat de request wordt gemaakt, roept `iSendARequestTo()` eerst `expandUri()` aan
- `expandUri()` vindt `{game_system_id}` in de URI
- Vervangt het met de opgeslagen waarde `123`
- URI wordt `/game-systems/123`
- Request wordt uitgevoerd naar het juiste ID!

**Dit is krachtig omdat:**
- Je geen hardcoded IDs hoeft te gebruiken
- Tests blijven werken ongeacht welke IDs de database genereert
- Je responses van de ene step kunt gebruiken in de volgende step
- Het simuleert echte user flows (create → get → update → delete)

#### F. Assertions (Verificaties)
```php
public function theResponseStatusShouldBe(int $status): void
{
    if ($this->lastResponseStatus !== $status) {
        throw new \RuntimeException(
            "Expected status {$status}, got {$this->lastResponseStatus}. Response: " .
            json_encode($this->lastResponseJson, JSON_PRETTY_PRINT)
        );
    }
}
```

**Hoe assertions werken:**
1. De method vergelijkt de **verwachte** waarde met de **actuele** waarde
2. Als ze niet matchen, wordt een `RuntimeException` gegooid
3. Behat vangt deze exception en markeert de step als **failed**
4. Het test scenario stopt (volgende steps worden **geskipped**)
5. Je ziet in de output:
   - Welke step faalde
   - Wat er verwacht werd
   - Wat er daadwerkelijk was
   - De volledige response (voor debugging)

**Voorbeeld van een failure:**
```
Then the response status should be 201
  Expected status 201, got 422. Response: {
      "message": "The name field is required.",
      "errors": {
          "name": ["The name field is required."]
      }
  }
```

### 5. Feature File (`features/game_systems.feature`)
```gherkin
Feature: Game systems API
  In order to manage TTRPG game systems
  As an API client
  I want to create, list, and retrieve game systems

  Scenario: Create a game system and retrieve it
    When I send a POST request to /game-systems with body:
      """
      {
        "name": "Dungeons & Dragons 5e",
        "description": "The fifth edition of the world's greatest roleplaying game"
      }
      """
    Then the response status should be 201
    And the response should contain key "id"
    And the response should contain "name" with value "Dungeons & Dragons 5e"
    And I store the value of "id" from the response as "game_system_id"
    When I send a GET request to /game-systems/{game_system_id}
    Then the response status should be 200
    And the response should contain "name" with value "Dungeons & Dragons 5e"
    And the response should contain "description" with value "The fifth edition of the world's greatest roleplaying game"
```

**Feature File Structuur:**

1. **Feature**: De overkoepelende functionaliteit
   - Titel: "Game systems API"
   - Beschrijving (3 regels): Legt uit **waarom** deze feature bestaat
   - Gebruikt "In order to... As a... I want to..." format (user story)

2. **Scenario**: Een specifieke test case
   - Titel: "Create a game system and retrieve it"
   - Elk scenario is een volledige user flow

3. **Steps**: Individuele acties en verificaties
   - **Given**: Setup (niet gebruikt in dit voorbeeld)
   - **When**: Acties (API calls)
   - **Then**: Verificaties (assertions)
   - **And**: Voortzetting van de vorige step type

**Execution Flow:**

```
@BeforeScenario Hook
  ↓
  Boot Laravel + Run Migrations (fresh database)
  ↓
When I send a POST request... [CREATE]
  ↓
  performRequest("POST", "/game-systems", {...data...})
  ↓
  Laravel processes: Route → Controller → Validation → Database → Response
  ↓
  lastResponseStatus = 201
  lastResponseJson = {"id": 1, "name": "D&D 5e", ...}
  ↓
Then the response status should be 201 [ASSERT]
  ↓
  Check: lastResponseStatus === 201 ✓
  ↓
And the response should contain key "id" [ASSERT]
  ↓
  Check: "id" exists in lastResponseJson ✓
  ↓
And I store the value of "id"... [STORE]
  ↓
  storedValues["game_system_id"] = 1
  ↓
When I send a GET request to /game-systems/{game_system_id} [RETRIEVE]
  ↓
  expandUri("/game-systems/{game_system_id}") → "/game-systems/1"
  ↓
  performRequest("GET", "/game-systems/1", [])
  ↓
  Laravel processes: Route → Controller → Database → Response
  ↓
  lastResponseStatus = 200
  lastResponseJson = {"id": 1, "name": "D&D 5e", ...}
  ↓
Then the response status should be 200 [ASSERT]
  ↓
  Check: lastResponseStatus === 200 ✓
  ↓
And the response should contain "name" with value "Dungeons & Dragons 5e" [ASSERT]
  ↓
  Check: lastResponseJson["name"] === "Dungeons & Dragons 5e" ✓
  ↓
@AfterScenario Hook (implicit)
  ↓
  Database wordt gewist (in-memory → weg)
  Laravel instance wordt opgeruimd
```

### 6. Run Script (`run-behat.sh`)
```bash
#!/bin/bash
php -d error_reporting=24575 vendor/bin/behat "$@"
```

**Waarom dit script?**
- PHP 8.5 introduceert deprecation warnings voor oude PDO constants
- Laravel 11 gebruikt nog de oude syntax (maar heeft backwards compatibility)
- `-d error_reporting=24575` = `E_ALL & ~E_DEPRECATED`
  - Laat alle errors zien BEHALVE deprecations
  - 24575 = 32767 (E_ALL) - 8192 (E_DEPRECATED)
- `"$@"` = Geeft alle command line argumenten door
  - Je kunt dus doen: `./run-behat.sh --format=progress`

## Alle Beschikbare Steps

### Request Steps
```gherkin
When I send a GET request to /path
When I send a POST request to /path with body:
  """
  {"key": "value"}
  """
When I send a PUT request to /path with body:
When I send a PATCH request to /path with body:
When I send a DELETE request to /path
```

### Assertion Steps
```gherkin
Then the response status should be 200
Then the response should contain key "id"
Then the response should contain "name" with value "John"
Then the response should be a JSON array
```

### Utility Steps
```gherkin
And I store the value of "id" from the response as "user_id"
# Later gebruiken:
When I send a GET request to /users/{user_id}
```

## Hoe voeg je nieuwe steps toe?

### Voorbeeld: Test voor DELETE endpoint

**1. Schrijf de feature:**
```gherkin
Scenario: Delete a game system
  Given a game system exists with name "Test System"
  When I send a DELETE request to /game-systems/{game_system_id}
  Then the response status should be 204
  When I send a GET request to /game-systems/{game_system_id}
  Then the response status should be 404
```

**2. Run Behat:**
```bash
./run-behat.sh
```

**3. Behat zegt: "Undefined step!"**
```
>>> default suite has undefined steps. Please choose the context to generate snippets:
[1] FeatureContext
```

**4. Behat genereert snippet:**
```php
/**
 * @Given a game system exists with name :name
 */
public function aGameSystemExistsWithName($name)
{
    throw new PendingException();
}
```

**5. Implementeer de step in FeatureContext.php:**
```php
/**
 * @Given a game system exists with name :name
 */
public function aGameSystemExistsWithName(string $name): void
{
    $data = ['name' => $name, 'description' => 'Test description'];
    $this->performRequest('POST', '/game-systems', $data);
    $this->storedValues['game_system_id'] = $this->lastResponseJson['id'];
}
```

## Voordelen van deze Setup

1. **Leesbare Tests**: Product owners, designers, en developers kunnen de tests lezen
2. **Geen Mocking**: Tests gebruiken de echte applicatie flow
3. **Database Isolatie**: Elke test heeft een schone database
4. **Snelheid**: In-memory database = milliseconden per test
5. **Real-World Simulation**: Tests gaan door alle middleware, validatie, etc.
6. **Variable Substitution**: Maak complexe flows met afhankelijkheden
7. **Debugging**: Bij failures zie je de volledige request + response
8. **Reusable Steps**: Schrijf een step één keer, gebruik overal

## Test Output Voorbeeld

```
Feature: Game systems API

  Scenario: Create a game system and retrieve it
    When I send a POST request to /game-systems with body: ✓
    Then the response status should be 201 ✓
    And the response should contain key "id" ✓
    And the response should contain "name" with value "Dungeons & Dragons 5e" ✓
    And I store the value of "id" from the response as "game_system_id" ✓
    When I send a GET request to /game-systems/{game_system_id} ✓
    Then the response status should be 200 ✓
    And the response should contain "name" with value "Dungeons & Dragons 5e" ✓

2 scenarios (2 passed)
12 steps (12 passed)
0m0.26s (38.30Mb)
```

## Hoe Run Je de Tests?

### Optie 1: Via Composer (Cross-platform - Aanbevolen)
```bash
composer test           # Progress output
composer test:pretty    # Pretty output met kleuren
```

### Optie 2: Direct (als composer test niet werkt)
**Mac/Linux:**
```bash
php -d error_reporting=24575 vendor/bin/behat
```

**Windows:**
```bash
php -d error_reporting=24575 vendor\bin\behat
```

**Waarom `-d error_reporting=24575`?**
- PHP 8.5 heeft deprecation warnings voor oude PDO constants
- Laravel 11 gebruikt backwards compatibility code
- Deze flag onderdrukt alleen deprecation warnings, niet echte errors
- 24575 = E_ALL & ~E_DEPRECATED

## Tips voor Nieuwe Features

1. **Start Simpel**: Begin met één scenario
2. **Red-Green-Refactor**: 
   - Schrijf de feature (Red - faalt)
   - Implementeer de code (Green - slaagt)
   - Refactor en improve
3. **Hergebruik Steps**: Probeer bestaande steps te gebruiken
4. **Background Blocks**: Voor setup die in elk scenario herhaalt:
   ```gherkin
   Background:
     Given I am logged in as "admin"
   
   Scenario: Create game system
     When I send a POST request...
   ```
5. **Scenario Outlines**: Voor data-driven tests:
   ```gherkin
   Scenario Outline: Create different game systems
     When I send a POST request to /game-systems with name "<name>"
     Then the response status should be 201
     
     Examples:
       | name        |
       | D&D 5e      |
       | Pathfinder  |
       | Call of Cthulhu |
   ```

## Conclusie

Deze Behat setup geeft je een krachtig framework voor API testing waarbij:
- **Natuurlijke taal** wordt vertaald naar **PHP code** via **regex matching**
- **Laravel** wordt **volledig opgestart** voor elke test (inclusief middleware, routes, database)
- **In-memory database** zorgt voor **snelle, geïsoleerde tests**
- **Variable substitution** maakt **complexe flows** mogelijk
- **Assertions** geven **duidelijke feedback** bij failures

De magische koppeling tussen Gherkin steps en PHP methods gebeurt via annotations en regex patterns, waarbij Behat automatisch de juiste method vindt en parameters extraheert uit de step tekst.
