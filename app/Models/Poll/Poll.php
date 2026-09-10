<?php

namespace App\Models\Poll;

use App\Models\Forum\ForumThread;
use App\Models\Page;
use App\Models\Status\Status;
use App\Models\Ticket\Ticket;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Str;

class Poll extends Model
{
    /**
     * Models a poll can be attached to, keyed by the short name used in
     * the admin API (`pollable=thread|status|page|ticket`).
     */
    public const POLLABLE_TYPES = [
        'thread' => ForumThread::class,
        'status' => Status::class,
        'page'   => Page::class,
        'ticket' => Ticket::class,
    ];

    protected $fillable = [
        'pollable_type',
        'pollable_id',
        'title',
        'description',
        'type',
        'anonymous',
        'closes_at',
        'created_by',
    ];

    protected $casts = [
        'anonymous'  => 'boolean',
        'closes_at'  => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    protected $appends = ['is_open', 'total_votes'];

    public function pollable(): MorphTo
    {
        return $this->morphTo();
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function options(): HasMany
    {
        return $this->hasMany(PollOption::class)->orderBy('position');
    }

    public function votes(): HasMany
    {
        return $this->hasMany(PollVote::class);
    }

    public function scopeOpen(Builder $query): Builder
    {
        return $query->where(function ($q) {
            $q->whereNull('closes_at')->orWhere('closes_at', '>', now());
        });
    }

    public function scopeClosed(Builder $query): Builder
    {
        return $query->whereNotNull('closes_at')->where('closes_at', '<=', now());
    }

    public function getIsOpenAttribute(): bool
    {
        if ($this->closes_at === null) {
            return true;
        }

        return $this->closes_at->isFuture();
    }

    public function getTotalVotesAttribute(): int
    {
        if ($this->relationLoaded('votes')) {
            return $this->votes->count();
        }

        return $this->votes()->count();
    }

    public function hasVoted(?User $user): bool
    {
        return $user !== null && count($this->userVotes($user)) > 0;
    }

    public function userVotes(?User $user): array
    {
        if ( ! $user) {
            return [];
        }

        if ($this->relationLoaded('votes')) {
            return $this->votes->where('user_id', $user->id)->pluck('poll_option_id')->values()->toArray();
        }

        return $this->votes()
            ->where('user_id', $user->id)
            ->pluck('poll_option_id')
            ->toArray();
    }

    public function results(): array
    {
        $totalVotes = $this->total_votes;
        $byOption   = $this->relationLoaded('votes')
            ? $this->votes->countBy('poll_option_id')
            : $this->votes()->selectRaw('poll_option_id, count(*) as aggregate')
                ->groupBy('poll_option_id')
                ->pluck('aggregate', 'poll_option_id');

        return $this->options->map(function ($option) use ($totalVotes, $byOption) {
            $voteCount  = (int) ($byOption[$option->id] ?? 0);
            $percentage = $totalVotes > 0 ? ($voteCount / $totalVotes) * 100 : 0;

            return [
                'id'          => $option->id,
                'option_text' => $option->option_text,
                'votes'       => $voteCount,
                'percentage'  => round($percentage, 1),
            ];
        })->values()->toArray();
    }

    /**
     * Creator or admin may edit/delete the poll.
     */
    public function canManage(?User $user): bool
    {
        if ( ! $user) {
            return false;
        }

        return $this->created_by === $user->id || $user->isAdmin();
    }

    /**
     * The JSON shape every endpoint returns for a poll.
     */
    public function toPayload(?User $user): array
    {
        $this->loadMissing(['creator', 'options', 'votes']);

        return [
            'id'            => $this->id,
            'pollable_type' => $this->pollable_type,
            'pollable_id'   => $this->pollable_id,
            'title'         => $this->title,
            'description'   => $this->description,
            'type'          => $this->type,
            'anonymous'     => $this->anonymous,
            'closes_at'     => $this->closes_at?->toIso8601String(),
            'is_open'       => $this->is_open,
            'total_votes'   => $this->total_votes,
            'creator'       => [
                'id'       => $this->creator?->id,
                'name'     => $this->creator?->name,
                'username' => $this->creator?->username,
            ],
            'options' => $this->options->map(function ($option) {
                return [
                    'id'          => $option->id,
                    'option_text' => $option->option_text,
                    'position'    => $option->position,
                ];
            })->values()->toArray(),
            'results'    => $this->results(),
            'user_votes' => $this->userVotes($user),
            'has_voted'  => $this->hasVoted($user),
            'can_edit'   => $this->canManage($user),
            'can_delete' => $this->canManage($user),
            'created_at' => $this->created_at->toIso8601String(),
            'updated_at' => $this->updated_at->toIso8601String(),
        ];
    }

    /**
     * Short name for the pollable class ('thread', 'status', ...), or null.
     */
    public function pollableKind(): ?string
    {
        $kind = array_search($this->pollable_type, self::POLLABLE_TYPES, true);

        return $kind === false ? null : $kind;
    }

    /**
     * Compact description of what the poll is attached to, for admin lists.
     */
    public function pollableSummary(): ?array
    {
        $kind = $this->pollableKind();
        if ( ! $kind) {
            return null;
        }

        $model = $this->pollable;
        if ( ! $model) {
            return ['type' => $kind, 'title' => null, 'url' => null];
        }

        return match ($kind) {
            'thread' => ['type' => $kind, 'title' => $model->title, 'url' => $model->url],
            'status' => ['type' => $kind, 'title' => Str::limit(trim(strip_tags($model->content ?? '')), 80), 'url' => '/timeline'],
            'page'   => ['type' => $kind, 'title' => $model->title, 'url' => '/wiki/' . $model->slug],
            'ticket' => ['type' => $kind, 'title' => $model->title, 'url' => '/admin/tickets'],
        };
    }
}
