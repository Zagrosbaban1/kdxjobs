@extends('layouts.kdx-auth')

@section('content')
    <div class="auth-grid">
        <section>
            <span class="eyebrow">New Password</span>
            <h1 class="hero-title">Choose a new password.</h1>
            <p class="lead">Use at least 10 characters with uppercase, lowercase, and a number.</p>
        </section>

        <section class="card">
            <form class="form" method="post" action="{{ route('password.update') }}">
                @csrf
                <input type="hidden" name="token" value="{{ $token }}">
                <label class="label">Email
                    <input class="input" type="email" name="email" value="{{ old('email', $email) }}" required autofocus>
                </label>
                <label class="label">Password
                    <input class="input" type="password" name="password" required>
                </label>
                <label class="label">Confirm Password
                    <input class="input" type="password" name="password_confirmation" required>
                </label>
                <div class="form-actions">
                    <button class="btn primary" type="submit">Reset Password</button>
                    <a class="btn" href="{{ url('/index.php') }}?page=login">Back to Login</a>
                </div>
            </form>
        </section>
    </div>
@endsection
