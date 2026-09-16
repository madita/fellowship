<?php

namespace App\Models\Ticket;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A member following a ticket: notified about new public comments and status changes.
 */
class TicketWatcher extends Model
{
    protected $fillable = [
        'ticket_id',
        'user_id',
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
}
