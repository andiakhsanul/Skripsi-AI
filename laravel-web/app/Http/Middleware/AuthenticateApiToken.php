<?php

namespace App\Http\Middleware;

use App\Models\ApiToken;
use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class AuthenticateApiToken
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $token = $request->bearerToken();

        if (! $token) {
            return new JsonResponse([
                'status' => 'error',
                'message' => 'Bearer token wajib dikirim',
            ], 401);
        }

        $tokenHash = hash('sha256', $token);
        $tokenRecord = ApiToken::query()
            ->with('user')
            ->where('token_hash', $tokenHash)
            ->first();

        if (! $tokenRecord || ! $tokenRecord->user) {
            return new JsonResponse([
                'status' => 'error',
                'message' => 'Token tidak valid',
            ], 401);
        }

        if ($tokenRecord->isExpired()) {
            $tokenRecord->delete();

            return new JsonResponse([
                'status' => 'error',
                'message' => 'Token sudah kedaluwarsa',
            ], 401);
        }

        $tokenRecord->forceFill([
            'last_used_at' => now(),
        ])->save();

        $user = $tokenRecord->user;
        $request->setUserResolver(static fn () => $user);

        return $next($request);
    }
}
