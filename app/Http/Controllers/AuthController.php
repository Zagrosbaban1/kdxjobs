<?php

namespace App\Http\Controllers;

use App\Models\Company;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\View\View;

class AuthController extends Controller
{
    public function showLogin(): View
    {
        return view('auth.login');
    }

    public function showRegister(): View
    {
        return view('auth.register');
    }

    public function showForgotPassword(): View
    {
        return view('auth.forgot-password');
    }

    public function sendPasswordResetLink(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'email' => ['required', 'email', 'max:180'],
        ]);

        Password::sendResetLink([
            'email' => Str::lower($data['email']),
            'status' => 'active',
        ]);

        return back()->with('message', 'If that email belongs to an active account, a password reset link has been sent.');
    }

    public function showResetPassword(Request $request, string $token): View
    {
        return view('auth.reset-password', [
            'token' => $token,
            'email' => (string) $request->query('email', ''),
        ]);
    }

    public function resetPassword(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'token' => ['required', 'string'],
            'email' => ['required', 'email', 'max:180'],
            'password' => [
                'required',
                'string',
                'min:10',
                'regex:/[A-Z]/',
                'regex:/[a-z]/',
                'regex:/[0-9]/',
                'confirmed',
            ],
        ]);

        $status = Password::reset(
            [
                'email' => Str::lower($data['email']),
                'status' => 'active',
                'password' => $data['password'],
                'password_confirmation' => $request->input('password_confirmation'),
                'token' => $data['token'],
            ],
            function (User $user, string $password): void {
                $user->forceFill([
                    'password_hash' => Hash::make($password),
                ])->save();
            }
        );

        if ($status !== Password::PASSWORD_RESET) {
            return back()
                ->withInput($request->only('email'))
                ->with('error', 'The reset link is invalid or has expired.');
        }

        return redirect()->route('login')->with('message', 'Password reset successfully. You can login now.');
    }

    public function login(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);

        $throttleKey = $this->loginThrottleKey($request, $data['email']);

        if (RateLimiter::tooManyAttempts($throttleKey, 5)) {
            $seconds = RateLimiter::availableIn($throttleKey);

            return back()
                ->withInput($request->except('password'))
                ->with('error', "Too many login attempts. Please try again in {$seconds} seconds.");
        }

        $user = User::query()->where('email', $data['email'])->first();

        if (!$user || $user->status !== 'active' || !Hash::check($data['password'], $user->password_hash)) {
            RateLimiter::hit($throttleKey, 300);

            return back()->withInput($request->except('password'))->with('error', 'Invalid email or password.');
        }

        RateLimiter::clear($throttleKey);
        Auth::login($user);
        $request->session()->regenerate();

        return redirect()->route(match ($user->role) {
            'company' => 'company.dashboard',
            'admin', 'superadmin' => 'admin.dashboard',
            default => 'user.dashboard',
        })->with('message', 'Login successful.');
    }

    public function register(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'role' => ['required', 'in:jobseeker,company'],
            'full_name' => ['nullable', 'string', 'max:160'],
            'company_name' => ['nullable', 'string', 'max:180'],
            'email' => ['required', 'email', 'max:180', 'unique:users,email'],
            'phone' => ['nullable', 'string', 'max:60'],
            'password' => [
                'required',
                'string',
                'min:10',
                'regex:/[A-Z]/',
                'regex:/[a-z]/',
                'regex:/[0-9]/',
                'confirmed',
            ],
            'skills' => ['nullable', 'string'],
            'industry' => ['nullable', 'string', 'max:160'],
            'location' => ['nullable', 'string', 'max:180'],
        ]);

        if ($data['role'] === 'jobseeker' && blank($data['full_name'] ?? null)) {
            return back()->withInput()->with('error', 'Full name is required for job seekers.');
        }

        if ($data['role'] === 'company' && (blank($data['company_name'] ?? null) || blank($data['industry'] ?? null) || blank($data['location'] ?? null))) {
            return back()->withInput()->with('error', 'Company name, industry, and location are required for company accounts.');
        }

        $user = DB::transaction(function () use ($data): User {
            $user = new User([
                'full_name' => $data['role'] === 'jobseeker' ? $data['full_name'] : null,
                'company_name' => $data['role'] === 'company' ? $data['company_name'] : null,
                'email' => $data['email'],
                'phone' => $data['phone'] ?: null,
                'skills' => $data['skills'] ?: null,
                'industry' => $data['industry'] ?: null,
                'location' => $data['location'] ?: null,
            ]);
            $user->role = $data['role'];
            $user->password_hash = Hash::make($data['password']);
            $user->status = 'active';
            $user->save();

            if ($data['role'] === 'company') {
                Company::query()->create([
                    'user_id' => $user->id,
                    'name' => $data['company_name'],
                    'industry' => $data['industry'],
                    'location' => $data['location'],
                    'verification_status' => 'pending',
                ]);
            }

            return $user;
        });

        Auth::login($user);
        $request->session()->regenerate();

        return redirect()->route($user->role === 'company' ? 'company.dashboard' : 'user.dashboard')
            ->with('message', 'Account created successfully.');
    }

    public function logout(Request $request): RedirectResponse
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('home')->with('message', 'Logged out.');
    }

    private function loginThrottleKey(Request $request, string $email): string
    {
        return Str::lower($email) . '|' . $request->ip();
    }
}
