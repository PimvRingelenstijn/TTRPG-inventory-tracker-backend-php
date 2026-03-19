Feature: Auth flow API
    In order to manage user authentication and authorization
    As an API client
    I want to register, login, and validate users

    Scenario: Successfully register a new user
        When I send a POST request to /auth/register with body:
        """
        {
          "email": "newuser@example.com",
          "username": "new-player",
          "password": "SecurePass123!"
        }
        """
        Then the response status should be 201
        And the response should contain "Message" with value "User registered successfully!"

    Scenario: Successfully login as a registered user
        Given a User exists with:
            | username    | test-user        |
        When I send a POST request to /auth/login with body:
        """
        {
          "email": "test@example.com",
          "password": "TestPass123!"
        }
        """
        Then the response status should be 200
        And the response should contain key "uuid"
        And the response should contain "email" with value "test@example.com"
        And the response should contain "username" with value "test-user"
        And the response should contain key "created_at"
        And I should receive an access token cookie
