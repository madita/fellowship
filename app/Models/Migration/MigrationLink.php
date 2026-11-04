<?php

namespace App\Models\Migration;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * One address the imported content points at, and the last word on
 * whether it still leads anywhere.
 *
 * Rows are keyed by the address rather than by the page holding it: the
 * same dead forum link sits in twenty wiki pages, and asking its server
 * twenty times would be rude as well as slow. Where it appears is kept
 * in `occurrences` so the report can say what to go and fix.
 */
class MigrationLink extends Model
{
    public const SCOPE_INTERNAL = 'internal';
    public const SCOPE_LEGACY   = 'legacy';
    public const SCOPE_EXTERNAL = 'external';

    public const STATUS_PENDING = 'pending';
    public const STATUS_OK      = 'ok';
    public const STATUS_BROKEN  = 'broken';
    // Something answered, but not clearly enough to call it either way:
    // a timeout, a login wall, a server having a bad day.
    public const STATUS_UNSURE  = 'unsure';
    public const STATUS_SKIPPED = 'skipped';

    public const KIND_LINK  = 'link';
    public const KIND_IMAGE = 'image';

    /**
     * How long a host may be to be stored as one.
     *
     * The width of a string column in this schema
     * (Schema::defaultStringLength), and well past the 253 characters a
     * hostname may have by specification: anything longer came out of a
     * malformed address rather than off a server.
     */
    public const MAX_HOST = 191;

    protected $fillable = [
        'url_hash',
        'url',
        'host',
        'scope',
        'kind',
        'status',
        'http_status',
        'redirect_url',
        'reason',
        'occurrences',
        'occurrence_count',
        'last_seen_at',
        'checked_at',
    ];

    protected $casts = [
        'occurrences'      => 'array',
        'occurrence_count' => 'integer',
        'http_status'      => 'integer',
        'last_seen_at'     => 'datetime',
        'checked_at'       => 'datetime',
    ];

    public static function hash(string $url): string
    {
        return hash('sha256', $url);
    }

    /**
     * Links still waiting for an answer.
     *
     * Only ever the ones never asked. A verdict already in hand is kept
     * until somebody asks for a recheck, which puts those rows back to
     * pending: were an inconclusive link asked again by itself, the
     * report would never finish, because every pass would leave the
     * same timeouts behind to be asked about once more.
     */
    public function scopeCheckable(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_PENDING);
    }

    public function scopeBroken(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_BROKEN);
    }
}
