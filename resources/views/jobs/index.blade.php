@extends('layouts.app')

@section('content')
    <div class="section-head">
        <div>
            <span class="eyebrow">Jobs</span>
            <h2>Explore open roles</h2>
            <p class="lead">This listing is fully driven by the Laravel app and new schema.</p>
        </div>
    </div>

    <form class="card form" method="get" style="margin-bottom: 24px;">
        <label class="label">Search
            <input class="input" type="text" name="q" value="{{ $search }}" placeholder="Title, company, location, or skill">
        </label>
        <div class="actions">
            <button class="btn primary" type="submit">Search Jobs</button>
            <a class="btn" href="{{ route('jobs.index') }}">Clear</a>
        </div>
    </form>

    <div class="grid grid-2">
        @forelse ($jobs as $job)
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
                    @auth
                        @if (auth()->user()->role === 'jobseeker')
                            <form method="post" action="{{ route('jobs.saved.toggle', $job) }}">
                                @csrf
                                <button class="btn" type="submit">
                                    {{ in_array($job->id, $savedIds, true) ? 'Unsave' : 'Save Job' }}
                                </button>
                            </form>
                        @endif
                    @endauth
                </div>
            </article>
        @empty
            <div class="card">No jobs found.</div>
        @endforelse
    </div>

    @include('partials.pagination', ['paginator' => $jobs])
@endsection
