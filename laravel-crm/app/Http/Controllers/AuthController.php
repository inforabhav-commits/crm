<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\AuditService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    public function showLogin()
    {
        return view('auth.login');
    }

    public function login(Request $request, AuditService $audit)
    {
        $credentials = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);

        if (! Auth::attempt($credentials, $request->boolean('remember'))) {
            $audit->log('auth.login_failed', null, 'Failed login attempt for '.$credentials['email'], null, ['email' => $credentials['email']], null, $request);

            return back()
                ->withErrors(['email' => 'The provided credentials do not match our records.'])
                ->onlyInput('email');
        }

        if (! Auth::user()->is_active) {
            $inactiveUser = Auth::user();
            Auth::logout();
            $audit->log('auth.login_failed', $inactiveUser, 'Inactive user login attempt.', null, ['email' => $inactiveUser->email], $inactiveUser, $request);

            throw ValidationException::withMessages([
                'email' => 'This user account is inactive.',
            ]);
        }

        $request->session()->regenerate();
        $audit->log('auth.login', Auth::user(), 'User logged in.', null, ['email' => Auth::user()->email], Auth::user(), $request);

        return redirect()->intended(route('dashboard'));
    }

    public function logout(Request $request, AuditService $audit)
    {
        $user = $request->user();
        if ($user instanceof User) {
            $audit->log('auth.logout', $user, 'User logged out.', null, ['email' => $user->email], $user, $request);
        }

        Auth::logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }
}
