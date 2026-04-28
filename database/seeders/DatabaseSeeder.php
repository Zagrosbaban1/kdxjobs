<?php

namespace Database\Seeders;

use App\Models\Company;
use App\Models\Job;
use App\Models\JobTag;
use App\Models\User;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $jobseeker = User::query()->firstOrCreate([
            'email' => 'zagros@example.com',
        ], [
            'role' => 'jobseeker',
            'full_name' => 'Zagros Baban',
            'phone' => '+964 750 000 0000',
            'password_hash' => '$2y$10$9jJwENmg2M.HMoeTCe5LGuS8CFTFvG4ZIrn/t7KfPabjN6eT9FESy',
            'skills' => 'SQL, Tableau, Power BI, Python',
            'status' => 'active',
        ]);

        $companyUser = User::query()->firstOrCreate([
            'email' => 'company@example.com',
        ], [
            'role' => 'company',
            'company_name' => 'BlueTech Solutions',
            'phone' => '+964 750 111 1111',
            'password_hash' => '$2y$10$9jJwENmg2M.HMoeTCe5LGuS8CFTFvG4ZIrn/t7KfPabjN6eT9FESy',
            'industry' => 'Data & AI',
            'location' => 'Erbil, Iraq',
            'status' => 'active',
        ]);

        $admin = User::query()->firstOrCreate([
            'email' => 'admin@example.com',
        ], [
            'role' => 'admin',
            'full_name' => 'Admin',
            'password_hash' => '$2y$10$9jJwENmg2M.HMoeTCe5LGuS8CFTFvG4ZIrn/t7KfPabjN6eT9FESy',
            'status' => 'active',
        ]);

        User::query()->firstOrCreate([
            'email' => 'superadmin@example.com',
        ], [
            'role' => 'superadmin',
            'full_name' => 'Super Admin',
            'password_hash' => '$2y$10$9jJwENmg2M.HMoeTCe5LGuS8CFTFvG4ZIrn/t7KfPabjN6eT9FESy',
            'status' => 'active',
        ]);

        $blueTech = Company::query()->firstOrCreate([
            'name' => 'BlueTech Solutions',
        ], [
            'user_id' => $companyUser->id,
            'industry' => 'Data & AI',
            'location' => 'Erbil, Iraq',
            'description' => 'Business intelligence, data platforms, and AI services.',
        ]);

        $cloudNova = Company::query()->firstOrCreate([
            'name' => 'CloudNova',
        ], [
            'industry' => 'Software',
            'location' => 'Remote',
            'description' => 'Remote-first software engineering team.',
        ]);

        $talentBridge = Company::query()->firstOrCreate([
            'name' => 'TalentBridge',
        ], [
            'industry' => 'Recruitment',
            'location' => 'Baghdad, Iraq',
            'description' => 'Hiring and HR operations partner.',
        ]);

        $jobs = [
            [
                'company_id' => $blueTech->id,
                'recruiter_id' => $admin->id,
                'title' => 'Data Analyst',
                'location' => 'Erbil, Iraq',
                'salary' => '$1,200 - $1,800',
                'type' => 'Full-time',
                'description' => 'Collect, clean, analyze, and visualize business data. Build dashboards, track KPIs, and recommend improvements.',
                'requirements' => 'Strong SQL, Power BI, Tableau, communication, and problem-solving skills.',
                'tags' => ['SQL', 'Power BI', 'Tableau'],
            ],
            [
                'company_id' => $cloudNova->id,
                'title' => 'Frontend Developer',
                'location' => 'Remote',
                'salary' => '$1,500 - $2,400',
                'type' => 'Remote',
                'description' => 'Build responsive interfaces, connect APIs, and improve product UX across web applications.',
                'requirements' => 'React, Tailwind CSS, REST APIs, accessibility, and clean component architecture.',
                'tags' => ['React', 'Tailwind', 'API'],
            ],
            [
                'company_id' => $talentBridge->id,
                'title' => 'HR Specialist',
                'location' => 'Baghdad, Iraq',
                'salary' => '$900 - $1,300',
                'type' => 'Hybrid',
                'description' => 'Coordinate recruitment, screen candidates, maintain HR records, and support hiring managers.',
                'requirements' => 'Recruitment, screening, interviewing, HR processes, and clear communication.',
                'tags' => ['Recruitment', 'Screening', 'HR'],
            ],
        ];

        foreach ($jobs as $jobData) {
            $tags = $jobData['tags'];
            unset($jobData['tags']);

            $job = Job::query()->firstOrCreate([
                'company_id' => $jobData['company_id'],
                'title' => $jobData['title'],
            ], $jobData);

            foreach ($tags as $tag) {
                JobTag::query()->firstOrCreate([
                    'job_id' => $job->id,
                    'tag' => $tag,
                ]);
            }
        }

        $jobseeker->applications()->firstOrCreate([
            'job_id' => Job::query()->where('title', 'Data Analyst')->value('id'),
            'applicant_email' => 'zagros@example.com',
        ], [
            'applicant_name' => 'Zagros Baban',
            'applicant_phone' => '+964 750 000 0000',
            'role' => 'Data Analyst',
            'cover_note' => 'I can build dashboards and KPI reports.',
            'status' => 'New',
        ]);

        \App\Models\BlogPost::query()->firstOrCreate([
            'title' => 'How to prepare for your first interview',
        ], [
            'author_id' => $admin->id,
            'excerpt' => 'A simple checklist to help candidates walk into interviews more confidently.',
            'content' => 'Research the company, read the role carefully, prepare stories from your own work, and practice answering clearly. Strong interviews are usually about clarity and preparation, not perfection.',
            'category' => 'Career Advice',
            'status' => 'published',
        ]);
    }
}
