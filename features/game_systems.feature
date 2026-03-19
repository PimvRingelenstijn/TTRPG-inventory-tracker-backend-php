Feature: Game systems API
    In order to manage TTRPG game systems
    As an API client
    I want to create, list, and retrieve game systems

    Scenario: Create a new game system
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
        And the response should contain "description" with value "The fifth edition of the world's greatest roleplaying game"
        And I store the value of "id" from the response as "game_system_id"

    Scenario: Retrieve an existing game system
        Given a GameSystem exists with:
            | name        | Dungeons & Dragons 5e                                       |
            | description | The fifth edition of the world's greatest roleplaying game |
        When I send a GET request to /game-systems/{game_system_id}
        Then the response status should be 200
        And the response should contain "name" with value "Dungeons & Dragons 5e"
        And the response should contain "description" with value "The fifth edition of the world's greatest roleplaying game"

    Scenario: List all game systems
        Given there are 3 GameSystem records
        When I send a GET request to /game-systems
        Then the response status should be 200
        And the response should be a JSON array with 3 items
