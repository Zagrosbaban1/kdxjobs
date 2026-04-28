<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Application extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'job_id',
        'user_id',
        'applicant_name',
        'applicant_email',
        'applicant_phone',
        'role',
        'cover_note',
        'cv_file',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'created_at' => 'datetime',
        ];
    }

    public function job(): BelongsTo
    {
        return $this->belongsTo(Job::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
