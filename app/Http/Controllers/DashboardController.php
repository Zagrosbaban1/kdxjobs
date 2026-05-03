<?php

namespace App\Http\Controllers;

use App\Models\Application;
use App\Models\BlogPost;
use App\Models\Company;
use App\Models\Job;
use App\Models\SavedJob;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class DashboardController extends Controller
{
    public function user(): View
    {
        $user = Auth::user();

        $applications = Application::query()
            ->with('job.company')
            ->where('user_id', $user->id)
            ->latest('created_at')
            ->get();

        $savedJobs = SavedJob::query()
            ->with('job.company')
            ->where('user_id', $user->id)
            ->latest('created_at')
            ->get();

        return view('dashboard.user', compact('applications', 'savedJobs'));
    }

    public function company(): View
    {
        $user = Auth::user();
        $company = Company::query()->where('user_id', $user->id)->firstOrFail();
        $jobs = Job::query()->withCount('applications')->where('company_id', $company->id)->latest('created_at')->get();
        $applications = Application::query()
            ->with('job')
            ->whereHas('job', fn($query) => $query->where('company_id', $company->id))
            ->latest('created_at')
            ->get();

        return view('dashboard.company', compact('company', 'jobs', 'applications'));
    }

    public function admin(): View
    {
        $jobs = Job::query()->with('company')->latest('created_at')->get();
        $applications = Application::query()->with('job.company')->latest('created_at')->get();
        $users = User::query()->latest('created_at')->get();
        $posts = BlogPost::query()->latest('created_at')->get();

        $stats = [
            'users' => User::query()->count(),
            'companies' => Company::query()->count(),
            'jobs' => Job::query()->count(),
            'applications' => Application::query()->count(),
        ];

        return view('dashboard.admin', compact('jobs', 'applications', 'users', 'posts', 'stats'));
    }

    public function storeJob(Request $request): RedirectResponse
    {
        $user = Auth::user();
        abort_unless(in_array($user->role, ['company', 'admin', 'superadmin'], true), 403);

        $data = $request->validate([
            'company_id' => ['nullable', 'integer', 'exists:companies,id'],
            'title' => ['required', 'string', 'max:180'],
            'location' => ['required', 'string', 'max:180'],
            'salary' => ['required', 'string', 'max:120'],
            'type' => ['required', 'in:Full-time,Part-time,Remote,Hybrid,Contract'],
            'description' => ['required', 'string'],
            'requirements' => ['nullable', 'string'],
            'expires_at' => ['nullable', 'date'],
            'tags' => ['nullable', 'string'],
        ]);

        $companyId = $user->role === 'company'
            ? Company::query()->where('user_id', $user->id)->value('id')
            : (int) $data['company_id'];

        if ($user->role === 'company') {
            $isVerified = Company::query()
                ->where('id', $companyId)
                ->where('verification_status', 'verified')
                ->exists();

            abort_unless($isVerified, 403, 'Your company must be verified before posting jobs.');
        }

        $job = Job::query()->create([
            'company_id' => $companyId,
            'recruiter_id' => in_array($user->role, ['admin', 'superadmin'], true) ? $user->id : null,
            'title' => $data['title'],
            'location' => $data['location'],
            'salary' => $data['salary'],
            'type' => $data['type'],
            'description' => $data['description'],
            'requirements' => $data['requirements'] ?: null,
            'expires_at' => $data['expires_at'] ?: null,
            'status' => 'active',
        ]);

        foreach (array_filter(array_map('trim', explode(',', (string) ($data['tags'] ?? '')))) as $tag) {
            $job->tags()->create(['tag' => $tag]);
        }

        return back()->with('message', 'Job posted successfully.');
    }

    public function updateApplication(Request $request, Application $application): RedirectResponse
    {
        $user = Auth::user();
        abort_unless(in_array($user->role, ['company', 'admin', 'superadmin'], true), 403);

        $data = $request->validate([
            'status' => ['required', 'in:New,Reviewed,Shortlisted,Interview,Accepted,Rejected'],
        ]);

        if ($user->role === 'company') {
            $companyId = Company::query()->where('user_id', $user->id)->value('id');
            abort_unless($application->job()->where('company_id', $companyId)->exists(), 403);
        }

        if ($user->role === 'admin') {
            abort_unless($application->job()->where('recruiter_id', $user->id)->exists(), 403);
        }

        $application->update([
            'status' => $data['status'],
        ]);

        return back()->with('message', 'Application status updated.');
    }
}
