<?php

namespace App\Http\Controllers;

use App\DTOs\LoginRequest;
use App\DTOs\RegistrationRequest;
use App\Services\AuthService;
use App\Utils\CookieUtils;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Cookie;

class AuthController extends Controller
{
    public function __construct(
        private readonly AuthService $authService
    ) {}

    public function register(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'email' => 'required|email',
            'password' => 'required|string',
            'username' => 'required|string',
        ]);

        $dto = new RegistrationRequest(
            email: $validated['email'],
            password: $validated['password'],
            username: $validated['username'],
        );

        $result = $this->authService->registerUser($dto);

        return response()->json($result, 201);
    }

    public function login(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'email' => 'required|email',
            'password' => 'required|string',
        ]);

        $dto = new LoginRequest(
            email: $validated['email'],
            password: $validated['password'],
        );

        $loginResult = $this->authService->loginUser($dto);

        $response = response()->json($loginResult->user_info->toArray());
        CookieUtils::setAccessTokenCookie($response, $loginResult->access_token, $loginResult->expires);

        return $response;
    }

    public function logout(Request $request): JsonResponse
    {
        return response()
            ->json(['message' => 'Successfully logged out'])
            ->withCookie(Cookie::forget('access_token'));
    }

    public function me(Request $request): JsonResponse
    {
        $user = $request->attributes->get('supabase_user');
        $userData = $this->authService->getUserData($user);

        return response()->json($userData->toArray());
    }
}
