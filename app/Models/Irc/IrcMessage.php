<?php

namespace App\Models\Irc;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class IrcMessage extends Model
{
    use HasFactory;

    protected $fillable = [
        'irc_channel_id',
        'irc_connection_id',
        'type',
        'from_nick',
        'to_nick',
        'message',
        'emotion',
        'gesture',
        'bubble_type',
        'is_private',
        'is_mention',
        'sent_at',
    ];

    protected $casts = [
        'is_private' => 'boolean',
        'is_mention' => 'boolean',
        'sent_at' => 'datetime',
    ];

    /**
     * Get the channel.
     */
    public function channel(): BelongsTo
    {
        return $this->belongsTo(IrcChannel::class, 'irc_channel_id');
    }

    /**
     * Get the connection.
     */
    public function connection(): BelongsTo
    {
        return $this->belongsTo(IrcConnection::class, 'irc_connection_id');
    }

    /**
     * Check if this is a system message.
     */
    public function isSystem(): bool
    {
        return in_array($this->type, ['join', 'part', 'quit', 'notice']);
    }

    /**
     * Check if this is an action (/me).
     */
    public function isAction(): bool
    {
        return $this->type === 'action';
    }

    /**
     * Scope: Channel messages.
     */
    public function scopeInChannel($query, $channelId)
    {
        return $query->where('irc_channel_id', $channelId);
    }

    /**
     * Scope: Private messages.
     */
    public function scopePrivate($query)
    {
        return $query->where('is_private', true);
    }

    /**
     * Scope: Mentions.
     */
    public function scopeMentions($query)
    {
        return $query->where('is_mention', true);
    }
}
