<?php

namespace App\Models\Irc;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class IrcConnection extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'irc_server_id',
        'nickname',
        'username',
        'realname',
        'status',
        'auto_connect',
        'auto_join_channels',
        'connected_at',
        'disconnected_at',
    ];

    protected $casts = [
        'auto_connect' => 'boolean',
        'auto_join_channels' => 'array',
        'connected_at' => 'datetime',
        'disconnected_at' => 'datetime',
    ];

    /**
     * Get the user.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the IRC server.
     */
    public function server(): BelongsTo
    {
        return $this->belongsTo(IrcServer::class, 'irc_server_id');
    }

    /**
     * Get all channels for this connection.
     */
    public function channels(): HasMany
    {
        return $this->hasMany(IrcChannel::class);
    }

    /**
     * Get all messages for this connection.
     */
    public function messages(): HasMany
    {
        return $this->hasMany(IrcMessage::class);
    }

    /**
     * Check if connection is active.
     */
    public function isConnected(): bool
    {
        return $this->status === 'connected';
    }

    /**
     * Get joined channels.
     */
    public function joinedChannels(): HasMany
    {
        return $this->channels()->where('is_joined', true);
    }

    /**
     * Scope: Connected connections.
     */
    public function scopeConnected($query)
    {
        return $query->where('status', 'connected');
    }

    /**
     * Scope: For specific user.
     */
    public function scopeForUser($query, $userId)
    {
        return $query->where('user_id', $userId);
    }
}
