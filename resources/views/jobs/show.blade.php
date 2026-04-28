@extends('layouts.app')

@section('content')
    <div class="grid grid-2">
        <aside class="card">
            <div style="display:flex;justify-content:space-between;gap:14px;align-items:start;">
                <div>
                    <span class="eyebrow">Job Details</span>
                    <h2 style="margin-top: 14px;">{{ $job->title }}</h2>
                    <p class="lead" style="margin-top: 10px;">{{ $job->company?->name }}</p>
                </div>
                <span class="badge">{{ $job->type }}</span>
            </div>
            <div class="meta" style="margin-top: 18px;">
                <span>{{ $job->location }}</span>
                <span>{{ $job->salary }}</span>
                @if ($job->expires_at)
                    <span>Closes {{ $job->expires_at->format('M j, Y') }}</span>
                @endif
            </div>
            <div class="tags" style="margin-top: 18px;">
                @foreach ($job->tags as $tag)
                    <span class="tag">{{ $tag->tag }}</span>
                @endforeach
            </div>
            @auth
                @if (auth()->user()->role === 'jobseeker')
                    <form method="post" action="{{ route('jobs.saved.toggle', $job) }}" style="margin-top: 18px;">
                        @csrf
                        <button class="btn" type="submit">{{ $isSaved ? 'Unsave Job' : 'Save Job' }}</button>
                    </form>
                @endif
            @endauth
        </aside>

        <section class="card">
            <h3>Description</h3>
            <p class="lead" style="max-width:none;">{{ $job->description }}</p>
            @if ($job->requirements)
                <h3 style="margin-top: 24px;">Requirements</h3>
                <p class="lead" style="max-width:none;">{{ $job->requirements }}</p>
            @endif

            <div style="margin-top: 26px;">
                <h3>Apply Now</h3>
                @auth
                    @if (auth()->user()->role === 'jobseeker')
                        <form class="form" method="post" action="{{ route('jobs.apply', $job) }}" style="margin-top: 16px;">
                            @csrf
                            <label class="label">Full Name
                                <input class="input" name="applicant_name" value="{{ old('applicant_name', auth()->user()->full_name) }}" required>
                            </label>
                            <label class="label">Email
                                <input class="input" type="email" name="applicant_email" value="{{ old('applicant_email', auth()->user()->email) }}" required>
                            </label>
                            <label class="label">Phone
                                <input class="input" name="applicant_phone" value="{{ old('applicant_phone', auth()->user()->phone) }}">
                            </label>
                            <label class="label">Role
                                <input class="input" name="role" value="{{ old('role', $job->title) }}" required>
                            </label>
                            <label class="label">Cover Note
                                <textarea class="textarea" name="cover_note" rows="5">{{ old('cover_note') }}</textarea>
                            </label>
                            <button class="btn primary" type="submit">Submit Application</button>
                        </form>
                    @else
                        <div class="flash bad" style="margin-top: 16px;">Only job seeker accounts can apply.</div>
                    @endif
                @else
                    <div class="actions" style="margin-top: 16px;">
                        <a class="btn primary" href="{{ route('login') }}">Login</a>
                        <a class="btn" href="{{ route('register') }}">Register</a>
                    </div>
                @endauth
            </div>
        </section>
    </div>
@endsection
