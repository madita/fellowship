<?php

namespace App\Models\Sandbox;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SandboxComment extends Model
{
    protected $table = 'sandbox_thread_comments';

    protected $fillable = [
        'thread_id',
        'user_id',
        'content',
    ];

    public function thread(): BelongsTo
    {
        return $this->belongsTo(SandboxThread::class, 'thread_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
