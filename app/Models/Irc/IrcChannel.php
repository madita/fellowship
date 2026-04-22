<?php

namespace App\Models\Irc;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class IrcChannel extends Model
{
    use HasFactory;

    protected $fillable = [
        'irc_connection_id',
        'name',
        'topic',
        'is_joined',
        'is_favorite',
        'is_private',
        'notify_mentions',
        'joined_at',
        'last_message_at',
        'unread_count',
    ];

    protected $casts = [
        'is_joined' => 'boolean',
        'is_favorite' => 'boolean',
        'is_private' => 'boolean',
        'notify_mentions' => 'boolean',
        'joined_at' => 'datetime',
        'last_message_at' => 'datetime',
        'unread_count' => 'integer',
    ];

    /**
     * Get the IRC connection.
     */
    public function connection(): BelongsTo
    {
        return $this->belongsTo(IrcConnection::class, 'irc_connection_id');
    }

    /**
     * Get all messages in this channel.
     */
    public function messages(): HasMany
    {
        return $this->hasMany(IrcMessage::class)->orderBy('sent_at');
    }

    /**
     * Get recent messages.
     */
    public function recentMessages($limit = 100)
    {
        return $this->messages()->latest('sent_at')->limit($limit)->get()->reverse();
    }

    /**
     * Increment unread count.
     */
    public function incrementUnread(): void
    {
        $this->increment('unread_count');
        $this->update(['last_message_at' => now()]);
    }

    /**
     * Mark as read.
     */
    public function markAsRead(): void
    {
        $this->update(['unread_count' => 0]);
    }

    /**
     * Scope: Joined channels.
     */
    public function scopeJoined($query)
    {
        return $query->where('is_joined', true);
    }

    /**
     * Scope: Favorite channels.
     */
    public function scopeFavorites($query)
    {
        return $query->where('is_favorite', true);
    }
}
