<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Services\Auth\CredentialAuthenticationService;
use App\Services\Auth\LoginRateLimiter;
use Illuminate\Support\Facades\Auth;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class LoginController extends Controller
{
    public function __construct(
        private readonly CredentialAuthenticationService $credentialAuthenticationService,
        private readonly LoginRateLimiter $loginRateLimiter,
    ) {}

    public function create(): View
    {
        return view('pages.auth.login');
    }

    public function login(LoginRequest $request): RedirectResponse
    {
        $this->loginRateLimiter->ensureIsNotRateLimited($request);

        $credentials = $request->validated();
        $remember = $request->boolean('remember');

        $user = $this->credentialAuthenticationService->attempt(
            $credentials['email'],
            $credentials['password'],
            $credentials['role'],
        );

        if ($user) {
            $this->loginRateLimiter->clear($request);
            Auth::login($user, $remember);
            $request->session()->regenerate();

            return redirect()->intended(route('dashboard'));
        }

        $this->loginRateLimiter->hit($request);

        return back()
            ->withInput($request->only('email', 'role'))
            ->withErrors([
                'email' => 'Email atau kata sandi yang Anda masukkan salah.',
            ]);
    }

    public function logout(\Illuminate\Http\Request $request): RedirectResponse
    {
        Auth::logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }
}
