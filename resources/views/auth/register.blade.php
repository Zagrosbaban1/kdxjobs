@extends('layouts.app')

@section('content')
    <div class="grid grid-2">
        <section>
            <span class="eyebrow">Register</span>
            <h1 style="font-size: clamp(34px, 5vw, 58px);">Create your KDXJobs account.</h1>
            <p class="lead">Job seekers and companies can both register directly inside the Laravel app now.</p>
        </section>

        <section class="card">
            <form class="form" method="post" action="{{ route('register.store') }}">
                @csrf
                <label class="label">Role
                    <select class="select" name="role">
                        <option value="jobseeker" @selected(old('role') === 'jobseeker')>Job Seeker</option>
                        <option value="company" @selected(old('role') === 'company')>Company</option>
                    </select>
                </label>
                <label class="label">Full Name
                    <input class="input" name="full_name" value="{{ old('full_name') }}">
                </label>
                <label class="label">Company Name
                    <input class="input" name="company_name" value="{{ old('company_name') }}">
                </label>
                <label class="label">Email
                    <input class="input" type="email" name="email" value="{{ old('email') }}" required>
                </label>
                <label class="label">Phone
                    <input class="input" name="phone" value="{{ old('phone') }}">
                </label>
                <label class="label">Industry
                    <input class="input" name="industry" value="{{ old('industry') }}">
                </label>
                <label class="label">Location
                    <input class="input" name="location" value="{{ old('location') }}">
                </label>
                <label class="label">Skills
                    <textarea class="textarea" name="skills" rows="3">{{ old('skills') }}</textarea>
                </label>
                <label class="label">Password
                    <input class="input" type="password" name="password" required>
                </label>
                <label class="label">Confirm Password
                    <input class="input" type="password" name="password_confirmation" required>
                </label>
                <button class="btn primary" type="submit">Create Account</button>
            </form>
        </section>
    </div>
@endsection
