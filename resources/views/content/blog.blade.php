@extends('layouts.app')

@section('content')
    <div class="section-head">
        <div>
            <span class="eyebrow">Blog</span>
            <h2>Career articles</h2>
            <p class="lead">This blog section is ready for Laravel-managed content.</p>
        </div>
    </div>

    <div class="grid grid-2">
        @forelse ($posts as $post)
            <article class="card">
                <span class="badge">{{ $post->category ?: 'Career Advice' }}</span>
                <h3 style="margin-top: 14px;">{{ $post->title }}</h3>
                <p class="muted tiny" style="margin-top: 10px;">
                    {{ $post->author?->full_name ?: 'KDXJobs Team' }} &middot; {{ $post->created_at?->format('M j, Y') }}
                </p>
                <p style="margin-top: 16px;">{{ $post->excerpt ?: \Illuminate\Support\Str::limit(strip_tags($post->content), 180) }}</p>
            </article>
        @empty
            <div class="card">No published blog posts yet.</div>
        @endforelse
    </div>

    @include('partials.pagination', ['paginator' => $posts])
@endsection
