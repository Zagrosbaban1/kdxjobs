<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class EnsureRole
{
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        $user = Auth::user();

        if (!$user) {
            return redirect()->route('login')->with('error', 'Please log in first.');
        }

        if (!in_array($user->role, $roles, true)) {
            return redirect()->route('home')->with('error', 'You are not allowed to open that page.');
        }

        return $next($request);
    }
}
