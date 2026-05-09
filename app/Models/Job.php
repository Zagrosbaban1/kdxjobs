<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str as StringHelper;
use Illuminate\Support\Str;

class Job extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'public_id',
        'company_id',
        'recruiter_id',
        'title',
        'location',
        'salary',
        'type',
        'description',
        'requirements',
        'status',
        'expires_at',
    ];

    protected function casts(): array
    {
        return [
            'created_at' => 'datetime',
            'expires_at' => 'date',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (Job $job): void {
            if (blank($job->public_id)) {
                $job->public_id = 'job_' . StringHelper::random(24);
            }
        });
    }

    public function getRouteKeyName(): string
    {
        return 'public_id';
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function recruiter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recruiter_id');
    }

    public function tags(): HasMany
    {
        return $this->hasMany(JobTag::class);
    }

    public function applications(): HasMany
    {
        return $this->hasMany(Application::class);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query
            ->where('status', 'active')
            ->where(function (Builder $inner): void {
                $inner->whereNull('expires_at')
                    ->orWhereDate('expires_at', '>=', now()->toDateString());
            });
    }

    public function isOpen(): bool
    {
        return $this->status === 'active'
            && ($this->expires_at === null || $this->expires_at->isToday() || $this->expires_at->isFuture());
    }

    public function summary(int $limit = 180): string
    {
        return Str::limit(strip_tags($this->description), $limit);
    }
}
