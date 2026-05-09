@extends('layouts.app')

@section('content')
    <div class="section-head">
        <div>
            <span class="eyebrow">Job Seeker Dashboard</span>
            <h2>Your applications and saved jobs</h2>
        </div>
    </div>

    <div class="grid grid-2">
        <section class="card">
            <h3>Applications</h3>
            <table>
                <thead>
                    <tr><th>Job</th><th>Status</th><th>Applied</th></tr>
                </thead>
                <tbody>
                    @forelse ($applications as $application)
                        <tr>
                            <td>{{ $application->job?->title }}<br><span class="muted tiny">{{ $application->job?->company?->name }}</span></td>
                            <td>{{ $application->status }}</td>
                            <td>{{ $application->created_at?->format('M j, Y') }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="3">No applications yet.</td></tr>
                    @endforelse
                </tbody>
            </table>
            @include('partials.pagination', ['paginator' => $applications])
        </section>

        <section class="card">
            <h3>Saved Jobs</h3>
            <table>
                <thead>
                    <tr><th>Job</th><th>Company</th><th>Saved</th></tr>
                </thead>
                <tbody>
                    @forelse ($savedJobs as $saved)
                        <tr>
                            <td><a href="{{ route('jobs.show', $saved->job) }}">{{ $saved->job?->title }}</a></td>
                            <td>{{ $saved->job?->company?->name }}</td>
                            <td>{{ $saved->created_at?->format('M j, Y') }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="3">No saved jobs yet.</td></tr>
                    @endforelse
                </tbody>
            </table>
            @include('partials.pagination', ['paginator' => $savedJobs])
        </section>
    </div>
@endsection
