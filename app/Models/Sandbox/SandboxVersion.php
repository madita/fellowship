<?php

namespace App\Models\Sandbox;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SandboxVersion extends Model
{
    use HasFactory;
    protected $fillable = [
        'sandbox_id',
        'user_id',
        'title',
        'content',
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
