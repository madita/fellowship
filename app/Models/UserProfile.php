<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * The rest of what a member has told us about themselves.
 *
 * Two kinds of field live here. The shareable ones are the member's to show
 * or keep — each is listed in SHAREABLE with whether it starts out shown.
 * Everything else is for the site's own records and never leaves it, which
 * is why the public shape is built from a fixed list rather than by hiding
 * what we remember to hide.
 */
class UserProfile extends Model
{
    /**
     * The fields a member may choose to show, and whether they start shown.
     * A street address and a phone number are not here, and cannot be.
     */
    public const SHAREABLE = [
        'bio'      => true,
        'pronouns' => true,
        'city'     => true,
        'country'  => true,
        'website'  => true,
        'socials'  => true,
        // Nobody's date of birth goes out unless they say so
        'birthday' => false,
    ];

    /**
     * Kept for the site's records and shown to nobody else.
     */
    public const PRIVATE_FIELDS = [
        'phone',
        'address_line1',
        'address_line2',
        'postcode',
        'state',
    ];

    protected $fillable = [
        'bio',
        'pronouns',
        'city',
        'country',
        'website',
        'birthday',
        'socials',
        'phone',
        'address_line1',
        'address_line2',
        'postcode',
        'state',
        'visibility',
    ];

    protected $casts = [
        'socials'    => 'array',
        'visibility' => 'array',
        'birthday'   => 'date',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Whether the member lets others see this field.
     */
    public function shows(string $field): bool
    {
        if ( ! array_key_exists($field, self::SHAREABLE)) {
            return false;
        }

        return (bool) ($this->visibility[$field] ?? self::SHAREABLE[$field]);
    }

    /**
     * What anybody may see: only the shareable fields the member shows, and
     * only those with something in them.
     */
    public function publicShape(): array
    {
        $shown = [];

        foreach (array_keys(self::SHAREABLE) as $field) {
            if ( ! $this->shows($field)) {
                continue;
            }

            $value = $field === 'birthday' ? $this->birthday?->toDateString() : $this->{$field};

            if ($value === null || $value === '' || $value === []) {
                continue;
            }

            $shown[$field] = $value;
        }

        return $shown;
    }

    /**
     * What the member sees of their own, including what they keep back.
     */
    public function ownShape(): array
    {
        $own = ['visibility' => []];

        foreach (array_keys(self::SHAREABLE) as $field) {
            $own[$field]               = $field === 'birthday' ? $this->birthday?->toDateString() : $this->{$field};
            $own['visibility'][$field] = $this->shows($field);
        }

        foreach (self::PRIVATE_FIELDS as $field) {
            $own[$field] = $this->{$field};
        }

        return $own;
    }
}
