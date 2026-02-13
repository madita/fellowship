<?php

namespace App\Models\Ticket;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TicketWatcher extends Model
{
    use HasFactory;

    protected $fillable = [
        'ticket_id',
        'user_id',
        'notify_comments',
        'notify_status_change',
    ];

    protected $casts = [
        'notify_comments' => 'boolean',
        'notify_status_change' => 'boolean',
    ];

    /**
     * Get the ticket.
     */
    public function ticket(): BelongsTo
    {
        return $this->belongsTo(Ticket::class);
    }

    /**
     * Get the watching user.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Check if user wants comment notifications.
     */
    public function wantsCommentNotifications(): bool
    {
        return $this->notify_comments;
    }

    /**
     * Check if user wants status change notifications.
     */
    public function wantsStatusChangeNotifications(): bool
    {
        return $this->notify_status_change;
    }
}
