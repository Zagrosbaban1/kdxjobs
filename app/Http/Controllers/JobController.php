<?php

namespace App\Http\Controllers;

use App\Models\Application;
use App\Models\Job;
use App\Models\SavedJob;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class JobController extends Controller
{
    public function index(Request $request): View
    {
        $search = trim((string) $request->string('q'));

        $jobs = Job::query()
            ->with(['company', 'tags'])
            ->active()
            ->when($search !== '', function ($query) use ($search): void {
                $query->where(function ($inner) use ($search): void {
                    $inner->where('title', 'like', '%' . $search . '%')
                        ->orWhere('location', 'like', '%' . $search . '%')
                        ->orWhere('salary', 'like', '%' . $search . '%')
                        ->orWhereHas('company', fn($company) => $company->where('name', 'like', '%' . $search . '%'))
                        ->orWhereHas('tags', fn($tags) => $tags->where('tag', 'like', '%' . $search . '%'));
                });
            })
            ->latest('created_at')
            ->paginate(12)
            ->withQueryString();

        $savedIds = Auth::check() && Auth::user()->role === 'jobseeker'
            ? SavedJob::query()->where('user_id', Auth::id())->pluck('job_id')->all()
            : [];

        return view('jobs.index', [
            'jobs' => $jobs,
            'search' => $search,
            'savedIds' => $savedIds,
        ]);
    }

    public function show(Job $job): View
    {
        $job->load(['company', 'tags']);

        $isSaved = Auth::check() && Auth::user()->role === 'jobseeker'
            ? SavedJob::query()->where('user_id', Auth::id())->where('job_id', $job->id)->exists()
            : false;

        return view('jobs.show', [
            'job' => $job,
            'isSaved' => $isSaved,
        ]);
    }

    public function apply(Request $request, Job $job): RedirectResponse
    {
        abort_unless(Auth::check() && Auth::user()->role === 'jobseeker', 403);

        $data = $request->validate([
            'applicant_name' => ['required', 'string', 'max:160'],
            'applicant_email' => ['required', 'email', 'max:180'],
            'applicant_phone' => ['nullable', 'string', 'max:60'],
            'role' => ['required', 'string', 'max:180'],
            'cover_note' => ['nullable', 'string'],
        ]);

        Application::query()->create([
            'job_id' => $job->id,
            'user_id' => Auth::id(),
            'applicant_name' => $data['applicant_name'],
            'applicant_email' => $data['applicant_email'],
            'applicant_phone' => $data['applicant_phone'] ?: null,
            'role' => $data['role'],
            'cover_note' => $data['cover_note'] ?: null,
            'cv_file' => Auth::user()->cv_file,
            'status' => 'New',
        ]);

        return redirect()->route('user.dashboard')->with('message', 'Application submitted successfully.');
    }

    public function toggleSaved(Job $job): RedirectResponse
    {
        abort_unless(Auth::check() && Auth::user()->role === 'jobseeker', 403);

        $saved = SavedJob::query()->where('user_id', Auth::id())->where('job_id', $job->id)->first();

        if ($saved) {
            $saved->delete();
            return back()->with('message', 'Job removed from saved list.');
        }

        SavedJob::query()->create([
            'user_id' => Auth::id(),
            'job_id' => $job->id,
        ]);

        return back()->with('message', 'Job saved.');
    }
}
