<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class WebAuthController extends Controller
{
    /**
     * Show the login form.
     */
    public function showLogin(): View|RedirectResponse
    {
        if (Auth::check()) {
            return redirect()->route('dashboard');
        }

        return view('auth.login');
    }

    /**
     * Process web authentication login.
     */
    public function login(Request $request): RedirectResponse
    {
        $credentials = $request->validate([
            'email' => ['required', 'string'],
            'password' => ['required', 'string'],
        ], [
            'email.required' => 'Loginni kiriting.',
            'password.required' => 'Parolni kiritish shart.',
        ]);

        if (config('app.preview_mode') && app()->environment(['local', 'staging', 'testing'])
            && $credentials['email'] === 'admin') {
            $credentials['email'] = 'owner@preview.aquaoptom.test';
        }

        $remember = $request->boolean('remember');

        // Check if input is phone or email
        $loginField = filter_var($credentials['email'], FILTER_VALIDATE_EMAIL) ? 'email' : 'phone';
        $authAttempt = [
            $loginField => $credentials['email'],
            'password' => $credentials['password'],
        ];

        if (! Auth::attempt($authAttempt, $remember)) {
            return back()->withInput($request->only('email', 'remember'))->withErrors([
                'email' => 'Kiritilgan login yoki parol noto\'g\'ri.',
            ]);
        }

        $user = Auth::user();

        // Check active / blocked status
        if (! $user->isActive()) {
            Auth::logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return back()->withInput($request->only('email'))->withErrors([
                'email' => 'Hisobingiz bloklangan. Iltimos, do\'kon egasiga murojaat qiling.',
            ]);
        }

        $request->session()->regenerate();

        return redirect()->intended(route('dashboard'));
    }

    /**
     * Process logout.
     */
    public function logout(Request $request): RedirectResponse
    {
        Auth::logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }
}
