<?php

namespace App\Http\Controllers;

use App\Models\Company;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
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

    public function login(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);

        $user = User::query()->where('email', $data['email'])->first();

        if (!$user || $user->status !== 'active' || !Hash::check($data['password'], $user->password_hash)) {
            return back()->withInput($request->except('password'))->with('error', 'Invalid email or password.');
        }

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
            'password' => ['required', 'string', 'min:6', 'confirmed'],
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
            $user = User::query()->create([
                'role' => $data['role'],
                'full_name' => $data['role'] === 'jobseeker' ? $data['full_name'] : null,
                'company_name' => $data['role'] === 'company' ? $data['company_name'] : null,
                'email' => $data['email'],
                'phone' => $data['phone'] ?: null,
                'password_hash' => Hash::make($data['password']),
                'skills' => $data['skills'] ?: null,
                'industry' => $data['industry'] ?: null,
                'location' => $data['location'] ?: null,
                'status' => 'active',
            ]);

            if ($data['role'] === 'company') {
                Company::query()->create([
                    'user_id' => $user->id,
                    'name' => $data['company_name'],
                    'industry' => $data['industry'],
                    'location' => $data['location'],
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
}
