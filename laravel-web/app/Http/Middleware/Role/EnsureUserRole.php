<?php

namespace App\Http\Middleware\Role;

use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\HttpFoundation\Response;

class EnsureUserRole
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        $user = $request->user();

        if (! $user) {
            if (! $request->expectsJson() && ! $request->is('api/*')) {
                return redirect()->route('login');
            }

            return new JsonResponse([
                'status' => 'error',
                'message' => 'Akses membutuhkan autentikasi',
            ], 401);
        }

        if (! in_array($user->role, $roles, true)) {
            if (! $request->expectsJson() && ! $request->is('api/*')) {
                throw new HttpException(403, 'Anda tidak memiliki izin untuk mengakses halaman ini.');
            }

            return new JsonResponse([
                'status' => 'error',
                'message' => 'Anda tidak memiliki izin untuk resource ini',
            ], 403);
        }

        return $next($request);
    }
}
