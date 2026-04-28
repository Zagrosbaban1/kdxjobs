<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Company extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'user_id',
        'name',
        'industry',
        'location',
        'logo_file',
        'description',
    ];

    protected function casts(): array
    {
        return [
            'created_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function jobs(): HasMany
    {
        return $this->hasMany(Job::class);
    }

    public function activeJobs(): HasMany
    {
        return $this->jobs()->where('status', 'active');
    }
}
