@extends('layouts.app')

@section('content')
    <section class="hero">
        <span class="eyebrow">KDXJobs Laravel</span>
        <h1>Find the right job and move the whole hiring flow into Laravel.</h1>
        <p class="lead">
            The public job feed is already running from Laravel, and the next layers now include login,
            saved jobs, applications, company posting, and admin dashboards.
        </p>
    </section>

    <section class="grid grid-3" style="margin-bottom: 30px;">
        <article class="card stat">
            <strong>{{ number_format($stats['openJobs']) }}</strong>
            <span class="muted">Open jobs</span>
        </article>
        <article class="card stat">
            <strong>{{ number_format($stats['companies']) }}</strong>
            <span class="muted">Companies with listings</span>
        </article>
        <article class="card stat">
            <strong>{{ number_format($stats['tags']) }}</strong>
            <span class="muted">Skill tags shown publicly</span>
        </article>
    </section>

    <section>
        <div class="section-head">
            <div>
                <h2>Latest Open Roles</h2>
                <p class="lead">This is now reading directly from Laravel models and the new MySQL schema.</p>
            </div>
            <a class="btn primary" href="{{ route('jobs.index') }}">Browse All Jobs</a>
        </div>

        <div class="grid grid-2">
            @foreach ($jobs as $job)
                <article class="card job-card">
                    <div style="display:flex;justify-content:space-between;gap:14px;align-items:start;">
                        <div>
                            <h3>{{ $job->title }}</h3>
                            <div class="muted" style="margin-top: 6px;">{{ $job->company?->name }}</div>
                        </div>
                        <span class="badge">{{ $job->type }}</span>
                    </div>
                    <div class="meta">
                        <span>{{ $job->location }}</span>
                        <span>{{ $job->salary }}</span>
                    </div>
                    <div>{{ $job->summary() }}</div>
                    <div class="tags">
                        @foreach ($job->tags as $tag)
                            <span class="tag">{{ $tag->tag }}</span>
                        @endforeach
                    </div>
                    <div class="actions">
                        <a class="btn primary" href="{{ route('jobs.show', $job) }}">View Details</a>
                    </div>
                </article>
            @endforeach
        </div>
    </section>
@endsection
