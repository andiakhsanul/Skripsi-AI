<?php

namespace App\Http\Controllers\Auth;

use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Services\Auth\CredentialAuthenticationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

class LoginController extends Controller
{
    public function __construct(
        private readonly CredentialAuthenticationService $credentialAuthenticationService,
    ) {}

    /**
     * Handle a login request.
     */
    public function login(Request $request)
    {
        $credentials = $request->validate([
            'email'    => ['required', 'email'],
            'password' => ['required'],
            'role' => ['required', 'string', Rule::in(UserRole::values())],
        ]);

        $remember = $request->boolean('remember');

        $user = $this->credentialAuthenticationService->attempt(
            $credentials['email'],
            $credentials['password'],
            $credentials['role'],
        );

        if ($user) {
            Auth::login($user, $remember);
            $request->session()->regenerate();

            $destination = $user->role === UserRole::Admin->value
                ? route('admin.dashboard')
                : route('dashboard');

            return redirect()->intended($destination);
        }

        return back()
            ->withInput($request->only('email', 'role'))
            ->withErrors([
                'email' => 'Email atau kata sandi yang Anda masukkan salah.',
            ]);
    }

    /**
     * Log the user out.
     */
    public function logout(Request $request)
    {
        Auth::logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }
}
