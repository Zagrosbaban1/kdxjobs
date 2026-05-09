@extends('layouts.kdx-auth')

@section('content')
    <div class="auth-grid">
        <section>
            <span class="eyebrow">Password Reset</span>
            <h1 class="hero-title">Reset your password.</h1>
            <p class="lead">Enter your account email and we will send a secure reset link if the account exists.</p>
        </section>

        <section class="card">
            <form class="form" method="post" action="{{ route('password.email') }}">
                @csrf
                <label class="label">Email
                    <input class="input" type="email" name="email" value="{{ old('email') }}" required autofocus>
                </label>
                <div class="form-actions">
                    <button class="btn primary" type="submit">Send Reset Link</button>
                    <a class="btn" href="{{ url('/index.php') }}?page=login">Back to Login</a>
                </div>
            </form>
        </section>
    </div>
@endsection
