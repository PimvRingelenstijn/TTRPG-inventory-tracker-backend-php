<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use PHPSupabase\Service;
use Symfony\Component\HttpFoundation\Response;

class SupabaseAuthMiddleware
{
    public function __construct(
        private readonly Service $supabase
    ) {}

    /**
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $accessToken = $request->cookie('access_token');

        if (!$accessToken) {
            return response()->json(['detail' => 'Not authenticated'], Response::HTTP_UNAUTHORIZED);
        }

        try {
            $auth = $this->supabase->createAuth();
            $data = $auth->getUser($accessToken);

            if (!$data || ($data->aud ?? null) !== 'authenticated') {
                return response()->json(['detail' => 'Invalid authentication token'], Response::HTTP_UNAUTHORIZED);
            }

            $request->attributes->set('supabase_user', $data);
        } catch (\Exception $e) {
            return response()->json(['detail' => 'Invalid authentication token'], Response::HTTP_UNAUTHORIZED);
        }

        return $next($request);
    }
}
