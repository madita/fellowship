<?php

namespace App\Models\Mail;

use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * One send: what it said, who it was for, and how it went.
 *
 * The wording is copied onto the mailing rather than read back through
 * the template, so editing a template next month does not rewrite what
 * went out last month. The counts are kept here as the send progresses -
 * the recipient rows are the truth, these are the summary a list can show
 * without aggregating thousands of rows per request.
 */
class Mailing extends Model
{
    use HasFactory;

    public const DRAFT     = 'draft';
    public const SCHEDULED = 'scheduled';
    public const SENDING   = 'sending';
    public const SENT      = 'sent';
    public const FAILED    = 'failed';
    public const CANCELLED = 'cancelled';

    public const STATUSES = [
        self::DRAFT,
        self::SCHEDULED,
        self::SENDING,
        self::SENT,
        self::FAILED,
        self::CANCELLED,
    ];

    protected $fillable = [
        'user_id',
        'mail_category_id',
        'mail_template_id',
        'subject',
        'body',
        'action_label',
        'action_url',
        'audience',
        'status',
        'scheduled_for',
        'started_at',
        'finished_at',
        'recipient_count',
        'sent_count',
        'failed_count',
        'skipped_count',
    ];

    protected $casts = [
        'audience'        => 'array',
        'scheduled_for'   => 'datetime',
        'started_at'      => 'datetime',
        'finished_at'     => 'datetime',
        'recipient_count' => 'integer',
        'sent_count'      => 'integer',
        'failed_count'    => 'integer',
        'skipped_count'   => 'integer',
    ];

    protected $appends = ['progress'];

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(MailCategory::class, 'mail_category_id');
    }

    public function template(): BelongsTo
    {
        return $this->belongsTo(MailTemplate::class, 'mail_template_id');
    }

    public function recipients(): HasMany
    {
        return $this->hasMany(MailingRecipient::class);
    }

    /**
     * How far along the send is, 0 to 100. A mailing with no recipients
     * at all is finished rather than stuck, so it reads 100.
     */
    public function getProgressAttribute(): int
    {
        if ($this->recipient_count < 1) {
            return in_array($this->status, [self::SENT, self::FAILED, self::CANCELLED], true) ? 100 : 0;
        }

        $done = $this->sent_count + $this->failed_count + $this->skipped_count;

        return (int) min(100, round($done / $this->recipient_count * 100));
    }

    /**
     * Still being written, so still freely editable.
     */
    public function isEditable(): bool
    {
        return in_array($this->status, [self::DRAFT, self::SCHEDULED], true);
    }

    /**
     * Due to go out, and nobody has started it. A mailing whose time has
     * passed while the scheduler was down is still due - late is better
     * than never for a newsletter.
     */
    public function scopeDue(Builder $query): Builder
    {
        return $query->where('status', self::SCHEDULED)
            ->whereNotNull('scheduled_for')
            ->where('scheduled_for', '<=', now());
    }

    public function scopeLatestFirst(Builder $query): Builder
    {
        return $query->orderByDesc('created_at')->orderByDesc('id');
    }
}
