<?php

namespace App\Models\Sandbox;

use App\Models\User;
use Cviebrock\EloquentSluggable\Sluggable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class Sandbox extends Model
{
    use HasFactory;
    use Sluggable;
    use SoftDeletes;

    protected $fillable = [
        'title',
        'slug',
        'uuid',
        'description',
        'user_id',
        'visibility',
        'content',
        'yjs_state',
        'settings',
        'last_edited_at',
        'last_edited_by',
    ];

    protected static function booted(): void
    {
        static::creating(function (Sandbox $sandbox) {
            if (empty($sandbox->uuid)) {
                $sandbox->uuid = (string) Str::uuid();
            }
        });
    }

    /**
     * Use uuid for route model binding.
     */
    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    protected $casts = [
        'settings' => 'array',
        'last_edited_at' => 'datetime',
    ];

    protected $hidden = [
        'yjs_state', // Don't include binary data in JSON responses
    ];

    public function sluggable(): array
    {
        return [
            'slug' => [
                'source' => 'title',
            ],
        ];
    }

    /**
     * The owner of the sandbox.
     */
    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /**
     * The user who last edited the sandbox.
     */
    public function lastEditor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'last_edited_by');
    }

    /**
     * Collaborators with their roles.
     */
    public function collaborators(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'sandbox_collaborators')
            ->withPivot(['role', 'invited_at', 'accepted_at'])
            ->withTimestamps();
    }

    /**
     * Version history snapshots.
     */
    public function versions(): HasMany
    {
        return $this->hasMany(SandboxVersion::class)->orderBy('created_at', 'desc');
    }

    /**
     * Check if user can view this sandbox.
     */
    public function canView(User $user): bool
    {
        if ($this->visibility === 'public') {
            return true;
        }

        if ($this->user_id === $user->id) {
            return true;
        }

        if ($this->visibility === 'members') {
            return $this->collaborators()->where('user_id', $user->id)->exists();
        }

        return $this->collaborators()
            ->where('user_id', $user->id)
            ->whereNotNull('accepted_at')
            ->exists();
    }

    /**
     * Check if user can edit this sandbox.
     */
    public function canEdit(User $user): bool
    {
        if ($this->user_id === $user->id) {
            return true;
        }

        return $this->collaborators()
            ->where('user_id', $user->id)
            ->whereIn('role', ['editor', 'admin'])
            ->whereNotNull('accepted_at')
            ->exists();
    }

    /**
     * Check if user can manage (admin) this sandbox.
     */
    public function canManage(User $user): bool
    {
        if ($this->user_id === $user->id) {
            return true;
        }

        return $this->collaborators()
            ->where('user_id', $user->id)
            ->where('role', 'admin')
            ->whereNotNull('accepted_at')
            ->exists();
    }

    /**
     * Get user's role in this sandbox.
     */
    public function getUserRole(User $user): ?string
    {
        if ($this->user_id === $user->id) {
            return 'owner';
        }

        $collaborator = $this->collaborators()->where('user_id', $user->id)->first();

        return $collaborator?->pivot?->role;
    }

    /**
     * Scope for sandboxes accessible by user.
     */
    public function scopeAccessibleBy($query, User $user)
    {
        return $query->where(function ($q) use ($user) {
            $q->where('user_id', $user->id)
                ->orWhere('visibility', 'public')
                ->orWhereHas('collaborators', function ($q) use ($user) {
                    $q->where('user_id', $user->id);
                });
        });
    }
}
