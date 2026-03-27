<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use PHPSupabase\Service;
use Symfony\Component\HttpFoundation\Response;

readonly class SupabaseAuthMiddleware
{
    public function __construct(
        private Service $supabase
    ) {}

    /**
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $accessToken = $request->cookie('access_token');

        if (!$accessToken) {
            Log::info('Auth middleware: No access_token cookie');
            return response()->json(['detail' => 'Not authenticated'], Response::HTTP_UNAUTHORIZED);
        }

        try {
            $auth = $this->supabase->createAuth();
            $data = $auth->getUser($accessToken);

            if (!$data || ($data->aud ?? null) !== 'authenticated') {
                Log::warning('Auth middleware: Token validation failed', ['aud' => $data?->aud ?? 'null']);
                return response()->json(['detail' => 'Invalid authentication token'], Response::HTTP_UNAUTHORIZED);
            }

            $request->attributes->set('supabase_user', $data);
        } catch (\Exception $e) {
            Log::warning('Auth middleware: Token validation error', [
                'message' => $e->getMessage(),
            ]);
            return response()->json(['detail' => 'Invalid authentication token'], Response::HTTP_UNAUTHORIZED);
        }

        return $next($request);
    }
}
