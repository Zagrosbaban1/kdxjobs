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
            ->limit(6)
            ->get();

        $stats = [
            'openJobs' => Job::query()->active()->count(),
            'companies' => Job::query()->active()->distinct('company_id')->count('company_id'),
            'tags' => $jobs->pluck('tags')->flatten()->count(),
        ];

        return view('home', [
            'jobs' => $jobs,
            'stats' => $stats,
        ]);
    }
}
