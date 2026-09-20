<?php

namespace App\Models\Irc;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * A comic chat character: a set of part choices the client draws from,
 * rather than a drawing of its own. The eight that ship with the site and
 * anything built in the character creator are the same kind of thing.
 */
class IrcComicCharacter extends Model
{
    use HasFactory;

    /**
     * The parts a character is made of, and what each one may be set to.
     * The client's own catalogue is the same list — see the comment there
     * if a part is added.
     */
    public const PARTS = [
        'body'      => ['slim', 'robe', 'boxy', 'sturdy'],
        'head'      => ['round', 'box', 'egg', 'helmet'],
        'ears'      => ['none', 'cat', 'round'],
        'sideParts' => ['none', 'floppy', 'panels', 'whiskers'],
        'hat'       => ['none', 'wizard', 'bandana', 'plume', 'antenna', 'feelers', 'crown'],
        'snout'     => ['none', 'cat', 'dog', 'beak'],
        'mask'      => ['none', 'ninja', 'visor'],
        'accessory' => ['none', 'beard', 'eyepatch', 'scarf', 'glasses'],
    ];

    public const TONES = ['normal', 'dark', 'metal', 'bright'];

    protected $fillable = [
        'key',
        'name',
        'spec',
        'is_enabled',
        'sort_order',
    ];

    protected $casts = [
        'spec'       => 'array',
        'is_enabled' => 'boolean',
        'is_builtin' => 'boolean',
        'sort_order' => 'integer',
    ];

    public function scopeEnabled(Builder $query): Builder
    {
        return $query->where('is_enabled', true);
    }

    public function scopeInOrder(Builder $query): Builder
    {
        return $query->orderBy('sort_order')->orderBy('id');
    }

    /**
     * The validation rules a spec has to satisfy, so a character can only
     * be built from parts the client knows how to draw.
     */
    public static function specRules(string $prefix = 'spec'): array
    {
        $rules = [
            $prefix            => ['required', 'array'],
            "{$prefix}.hue"    => ['required', 'integer', 'min:0', 'max:359'],
            "{$prefix}.tone"   => ['required', 'string', 'in:' . implode(',', self::TONES)],
        ];

        foreach (self::PARTS as $part => $options) {
            $rules["{$prefix}.{$part}"] = ['required', 'string', 'in:' . implode(',', $options)];
        }

        return $rules;
    }
}
