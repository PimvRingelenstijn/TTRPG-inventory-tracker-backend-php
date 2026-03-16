<?php

namespace App\Services;

use App\DTOs\LoginRequest;
use App\DTOs\LoginResult;
use App\DTOs\RegistrationRequest;
use App\DTOs\UserDataResponse;
use App\Mappers\AuthMapper;
use App\Mappers\UserMapper;
use App\Repositories\UserRepository;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Support\Facades\Log;
use PHPSupabase\Service;
use Symfony\Component\HttpFoundation\Response;

class AuthService
{
    public function __construct(
        private readonly Service $supabase,
        private readonly UserRepository $userRepository,
    ) {}

    public function registerUser(RegistrationRequest $request): array
    {
        try {
            $auth = $this->supabase->createAuth();
            $auth->createUserWithEmailAndPassword(
                $request->email,
                $request->password,
                ['username' => $request->username]
            );
            $data = $auth->data();

            $userAttrs = UserMapper::toUser($request, $data);
            $this->userRepository->create($userAttrs);

            return ['Message' => 'User registered successfully!'];
        } catch (\Exception $e) {
            throw new HttpResponseException(
                response()->json(
                    ['detail' => 'Registration failed: ' . $e->getMessage()],
                    Response::HTTP_BAD_REQUEST
                )
            );
        }
    }

    public function loginUser(LoginRequest $request): LoginResult
    {
        Log::info('Login attempt', ['email' => $request->email]);

        try {
            $auth = $this->supabase->createAuth();
            $auth->signInWithEmailAndPassword($request->email, $request->password);
            $data = $auth->data();

            $supabaseUserId = $data->user->id ?? null;
            if (!$supabaseUserId) {
                Log::warning('Login failed: Supabase response missing user id');
                throw new \Exception('Supabase response missing user id');
            }
            Log::info('Supabase auth succeeded', ['user_id' => $supabaseUserId]);

            $dbUser = $this->userRepository->getByUuid($supabaseUserId);
            if (!$dbUser) {
                Log::warning('Login failed: User not found in users table', ['user_id' => $supabaseUserId]);
                throw new \Exception('User not found in database');
            }
            Log::info('Login succeeded', ['user_id' => $supabaseUserId, 'username' => $dbUser->username]);

            $userDataResponse = AuthMapper::toUserDataResponse($data->user, $dbUser);
            return AuthMapper::toLoginResult($data, $userDataResponse);
        } catch (HttpResponseException $e) {
            throw $e;
        } catch (\Exception $e) {
            Log::warning('Login failed', [
                'email' => $request->email,
                'reason' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            throw new HttpResponseException(
                response()->json(
                    ['detail' => 'Invalid credentials'],
                    Response::HTTP_UNAUTHORIZED
                )
            );
        }
    }

    public function getUserData(object $authUser): UserDataResponse
    {
        $dbUser = $this->userRepository->getByUuid($authUser->id);
        if (!$dbUser) {
            throw new HttpResponseException(
                response()->json(
                    ['detail' => 'User not found'],
                    Response::HTTP_NOT_FOUND
                )
            );
        }

        return AuthMapper::toUserDataResponse($authUser, $dbUser);
    }
}
