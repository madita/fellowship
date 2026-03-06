<?php

namespace App\Models\Sandbox;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SandboxVersion extends Model
{
    protected $fillable = [
        'sandbox_id',
        'user_id',
        'title',
        'yjs_state',
    ];

    protected $hidden = [
        'yjs_state',
    ];

    public function sandbox(): BelongsTo
    {
        return $this->belongsTo(Sandbox::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
