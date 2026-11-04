<?php

namespace App\Models\Mail;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One member's copy of one mailing.
 *
 * Every one of these is written before anything is sent, which is what
 * makes a send resumable and a double-send impossible: the row moves from
 * pending to sent once, and a run that starts again only looks at what is
 * still pending.
 *
 * `email` is a copy taken when the mailing was prepared, so the record
 * still says where the mail actually went after a member changes address.
 */
class MailingRecipient extends Model
{
    use HasFactory;

    public const PENDING = 'pending';
    public const SENT    = 'sent';
    public const FAILED  = 'failed';
    public const SKIPPED = 'skipped';

    protected $fillable = [
        'mailing_id',
        'user_id',
        'email',
        'status',
        'reason',
        'sent_at',
    ];

    protected $casts = [
        'sent_at' => 'datetime',
    ];

    public function mailing(): BelongsTo
    {
        return $this->belongsTo(Mailing::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
