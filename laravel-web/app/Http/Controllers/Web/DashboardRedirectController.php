<?php

namespace App\Http\Controllers\Web;

use App\Enums\UserRole;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class DashboardRedirectController
{
    public function __invoke(Request $request): RedirectResponse
    {
        $destination = $request->user()?->role === UserRole::Admin->value
            ? route('admin.dashboard')
            : route('student.dashboard');

        return redirect()->to($destination);
    }
}
