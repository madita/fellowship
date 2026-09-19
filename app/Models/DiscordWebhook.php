<?php

namespace App\Models;

use App\Support\DiscordEvents;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A Discord channel that gets a message when one of the chosen events happens.
 */
class DiscordWebhook extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'url',
        'events',
        'is_active',
        'created_by_user_id',
    ];

    protected $casts = [
        // The address is the credential; it is never handed back to the browser
        'url'          => 'encrypted',
        'events'       => 'array',
        'is_active'    => 'boolean',
        'last_sent_at' => 'datetime',
    ];

    protected $hidden = ['url'];

    protected $appends = ['url_hint'];

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    /**
     * Enough of the address to recognise it, without giving it away.
     */
    public function getUrlHintAttribute(): string
    {
        $id = explode('/', rtrim((string) $this->url, '/'));

        return count($id) > 1 ? '…/' . $id[count($id) - 2] : '';
    }

    /**
     * Whether this webhook announces the given event.
     */
    public function handles(string $event): bool
    {
        return in_array($event, (array) $this->events, true);
    }

    /**
     * Only the events the site actually sends.
     */
    public function setEventsAttribute($events): void
    {
        $this->attributes['events'] = json_encode(
            array_values(array_intersect((array) $events, DiscordEvents::keys()))
        );
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }
}
