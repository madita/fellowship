<?php

namespace App\Models\Sandbox;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class SandboxThread extends Model
{
    protected $table = 'sandbox_comment_threads';

    protected $fillable = [
        'uuid',
        'sandbox_id',
        'user_id',
        'quote',
        'resolved_at',
    ];

    protected $casts = [
        'resolved_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::creating(function (SandboxThread $thread) {
            if (empty($thread->uuid)) {
                $thread->uuid = (string) Str::uuid();
            }
        });
    }

    public function sandbox(): BelongsTo
    {
        return $this->belongsTo(Sandbox::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function comments(): HasMany
    {
        return $this->hasMany(SandboxComment::class, 'thread_id')->orderBy('created_at', 'asc');
    }
}
