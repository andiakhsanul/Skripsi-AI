<?php

namespace App\Http\Middleware\Role;

use App\Enums\UserRole;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureStudentRole extends EnsureUserRole
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        return parent::handle($request, $next, UserRole::Mahasiswa->value);
    }
}
