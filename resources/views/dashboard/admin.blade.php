@extends('layouts.app')

@section('content')
    <div class="section-head">
        <div>
            <span class="eyebrow">Admin Dashboard</span>
            <h2>Platform overview</h2>
        </div>
    </div>

    <div class="grid grid-3">
        <article class="card stat"><strong>{{ $stats['users'] }}</strong><span class="muted">Users</span></article>
        <article class="card stat"><strong>{{ $stats['companies'] }}</strong><span class="muted">Companies</span></article>
        <article class="card stat"><strong>{{ $stats['applications'] }}</strong><span class="muted">Applications</span></article>
    </div>

    <div class="grid grid-2" style="margin-top: 22px;">
        <section class="card">
            <h3>Users</h3>
            <table>
                <thead><tr><th>Name</th><th>Role</th><th>Status</th></tr></thead>
                <tbody>
                    @foreach ($users as $user)
                        <tr>
                            <td>{{ $user->full_name ?: ($user->company_name ?: $user->email) }}</td>
                            <td>{{ $user->role }}</td>
                            <td>{{ $user->status }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
            @include('partials.pagination', ['paginator' => $users])
        </section>

        <section class="card">
            <h3>Jobs</h3>
            <table>
                <thead><tr><th>Title</th><th>Company</th><th>Status</th></tr></thead>
                <tbody>
                    @foreach ($jobs as $job)
                        <tr>
                            <td>{{ $job->title }}</td>
                            <td>{{ $job->company?->name }}</td>
                            <td>{{ $job->status }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
            @include('partials.pagination', ['paginator' => $jobs])
        </section>
    </div>

    <section class="card" style="margin-top: 22px;">
        <h3>Applications</h3>
        <table>
            <thead><tr><th>Applicant</th><th>Job</th><th>Status</th></tr></thead>
            <tbody>
                @foreach ($applications as $application)
                    <tr>
                        <td>{{ $application->applicant_name }}</td>
                        <td>{{ $application->job?->title }}</td>
                        <td>{{ $application->status }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
        @include('partials.pagination', ['paginator' => $applications])
    </section>
@endsection
