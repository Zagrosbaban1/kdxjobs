<?php

namespace App\Http\Controllers;

use App\Models\Job;
use Illuminate\Contracts\View\View;

class HomeController extends Controller
{
    public function index(): View
    {
        $jobs = Job::query()
            ->with(['company', 'tags'])
            ->active()
            ->latest('created_at')
            ->get();

        $stats = [
            'openJobs' => Job::query()->active()->count(),
            'companies' => $jobs->pluck('company_id')->unique()->count(),
            'tags' => $jobs->pluck('tags')->flatten()->count(),
        ];

        return view('home', [
            'jobs' => $jobs,
            'stats' => $stats,
        ]);
    }
}
