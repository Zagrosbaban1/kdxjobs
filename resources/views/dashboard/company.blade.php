@extends('layouts.app')

@section('content')
    <div class="section-head">
        <div>
            <span class="eyebrow">Company Dashboard</span>
            <h2>{{ $company->name }}</h2>
            <p class="lead">{{ $company->industry }} in {{ $company->location }}</p>
        </div>
    </div>

    <div class="grid grid-2">
        <section class="card">
            <h3>Post a Job</h3>
            <form class="form" method="post" action="{{ route('dashboard.jobs.store') }}">
                @csrf
                <label class="label">Title<input class="input" name="title" required></label>
                <label class="label">Location<input class="input" name="location" required></label>
                <label class="label">Salary<input class="input" name="salary" required></label>
                <label class="label">Type
                    <select class="select" name="type">
                        <option>Full-time</option>
                        <option>Part-time</option>
                        <option>Remote</option>
                        <option>Hybrid</option>
                        <option>Contract</option>
                    </select>
                </label>
                <label class="label">Description<textarea class="textarea" name="description" rows="5" required></textarea></label>
                <label class="label">Requirements<textarea class="textarea" name="requirements" rows="4"></textarea></label>
                <label class="label">Tags<input class="input" name="tags" placeholder="React, API, Hiring"></label>
                <label class="label">Expires At<input class="input" type="date" name="expires_at"></label>
                <button class="btn primary" type="submit">Publish Job</button>
            </form>
        </section>

        <section class="card">
            <h3>Your Jobs</h3>
            <table>
                <thead>
                    <tr><th>Job</th><th>Applications</th><th>Status</th></tr>
                </thead>
                <tbody>
                    @forelse ($jobs as $job)
                        <tr>
                            <td>{{ $job->title }}<br><span class="muted tiny">{{ $job->location }}</span></td>
                            <td>{{ $job->applications_count }}</td>
                            <td>{{ $job->status }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="3">No jobs posted yet.</td></tr>
                    @endforelse
                </tbody>
            </table>
            @include('partials.pagination', ['paginator' => $jobs])
        </section>
    </div>

    <section class="card" style="margin-top: 22px;">
        <h3>Applications</h3>
        <table>
            <thead>
                <tr><th>Applicant</th><th>Job</th><th>Status</th><th>Action</th></tr>
            </thead>
            <tbody>
                @forelse ($applications as $application)
                    <tr>
                        <td>{{ $application->applicant_name }}<br><span class="muted tiny">{{ $application->applicant_email }}</span></td>
                        <td>{{ $application->job?->title }}</td>
                        <td>{{ $application->status }}</td>
                        <td>
                            <form method="post" action="{{ route('dashboard.applications.update', $application) }}">
                                @csrf
                                <select class="select" name="status" onchange="this.form.submit()">
                                    @foreach (['New','Reviewed','Shortlisted','Interview','Accepted','Rejected'] as $status)
                                        <option value="{{ $status }}" @selected($application->status === $status)>{{ $status }}</option>
                                    @endforeach
                                </select>
                            </form>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="4">No applications yet.</td></tr>
                @endforelse
            </tbody>
        </table>
        @include('partials.pagination', ['paginator' => $applications])
    </section>
@endsection
