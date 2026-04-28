<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class JobTag extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'job_id',
        'tag',
    ];

    public function job(): BelongsTo
    {
        return $this->belongsTo(Job::class);
    }
}
