@extends('layouts.app')

@section('content')
    <div class="section-head">
        <div>
            <span class="eyebrow">Companies</span>
            <h2>Hiring companies</h2>
            <p class="lead">A Laravel-based companies page replacing the old custom PHP listing.</p>
        </div>
    </div>

    <div class="grid grid-2">
        @foreach ($companies as $company)
            <article class="card">
                <h3>{{ $company->name }}</h3>
                <p class="lead" style="font-size:16px;">{{ $company->industry }} · {{ $company->location }}</p>
                <p>{{ $company->description }}</p>
                <span class="badge">{{ $company->jobs_count }} jobs</span>
            </article>
        @endforeach
    </div>
@endsection
