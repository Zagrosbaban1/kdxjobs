@extends('layouts.app')

@section('content')
    <div class="grid grid-2">
        <section>
            <span class="eyebrow">Login</span>
            <h1 style="font-size: clamp(34px, 5vw, 58px);">Welcome back.</h1>
            <p class="lead">Use your KDXJobs account to manage applications, jobs, or admin tasks.</p>
        </section>

        <section class="card">
            <form class="form" method="post" action="{{ route('login.store') }}">
                @csrf
                <label class="label">Email
                    <input class="input" type="email" name="email" value="{{ old('email') }}" required>
                </label>
                <label class="label">Password
                    <input class="input" type="password" name="password" required>
                </label>
                <a class="tiny" href="{{ route('password.request') }}">Forgot password?</a>
                <button class="btn primary" type="submit">Login</button>
            </form>
        </section>
    </div>
@endsection
