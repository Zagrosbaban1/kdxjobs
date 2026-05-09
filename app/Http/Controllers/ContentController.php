<?php

namespace App\Http\Controllers;

use App\Models\BlogPost;
use App\Models\Company;
use Illuminate\View\View;

class ContentController extends Controller
{
    public function companies(): View
    {
        $companies = Company::query()
            ->withCount('jobs')
            ->orderBy('name')
            ->paginate(12)
            ->withQueryString();

        return view('content.companies', compact('companies'));
    }

    public function blog(): View
    {
        $posts = BlogPost::query()
            ->with('author')
            ->where('status', 'published')
            ->latest('created_at')
            ->paginate(10)
            ->withQueryString();

        return view('content.blog', compact('posts'));
    }
}
