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
use PHPSupabase\Service;
use Symfony\Component\HttpFoundation\Response;

class AuthService
{
    public function __construct(
        private readonly Service $supabase,
        private readonly UserRepository $userRepository,
    ) {
        error_log("AuthService constructor - supabase class: " . get_class($supabase));
    }

    // actual
//    public function registerUser(RegistrationRequest $request): array
//    {
//        try {
//            $auth = $this->supabase->createAuth();
//            $auth->createUserWithEmailAndPassword(
//                $request->email,
//                $request->password,
//                ['username' => $request->username]
//            );
//            $data = $auth->data();
//
//            $userAttrs = UserMapper::toUser($request, $data);
//            $this->userRepository->create($userAttrs);
//
//            return ['Message' => 'User registered successfully!'];
//        } catch (\Exception $e) {
//            throw new HttpResponseException(
//                response()->json(
//                    ['detail' => 'Registration failed: ' . $e->getMessage()],
//                    Response::HTTP_BAD_REQUEST
//                )
//            );
//        }
//    }

    //debug function
    public function registerUser(RegistrationRequest $request): array
    {
        error_log("=== AuthService::registerUser called ===");
        error_log("Email: " . $request->email);
        error_log("Username: " . $request->username);

        try {
            $auth = $this->supabase->createAuth();
            error_log("Got auth instance: " . get_class($auth));

            $auth->createUserWithEmailAndPassword(
                $request->email,
                $request->password,
                ['username' => $request->username]
            );
            error_log("Supabase user created successfully");

            $data = $auth->data();
            error_log("Supabase response: " . json_encode($data));

            $userAttrs = UserMapper::toUser($request, $data);
            error_log("User attributes: " . json_encode($userAttrs));

            $this->userRepository->create($userAttrs);
            error_log("User saved to database");

            return ['Message' => 'User registered successfully!'];
        } catch (\Exception $e) {
            error_log("!!! Exception in registerUser: " . get_class($e));
            error_log("Message: " . $e->getMessage());
            error_log("File: " . $e->getFile() . ":" . $e->getLine());
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
        try {
            $auth = $this->supabase->createAuth();
            $auth->signInWithEmailAndPassword($request->email, $request->password);
            $data = $auth->data();

            $dbUser = $this->userRepository->getByUuid($data->user->id);
            if (!$dbUser) {
                throw new \Exception('User not found in database');
            }

            $userDataResponse = AuthMapper::toUserDataResponse($data->user, $dbUser);
            return AuthMapper::toLoginResult($data, $userDataResponse);
        } catch (\Exception $e) {
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
