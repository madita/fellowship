<?php

namespace App\Models\Mail;

use Astrotomic\Translatable\Contracts\Translatable as TranslatableContract;
use Astrotomic\Translatable\Translatable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A kind of mail a member can say yes or no to.
 *
 * Opting out is per kind rather than all-or-nothing, so somebody who has
 * had enough of the newsletter still hears that the event they answered
 * has moved. `is_mandatory` marks the kind nobody may turn off - the mail
 * that gets them back into their account - and `default_opt_in` is the
 * answer for everybody who has never been asked.
 */
class MailCategory extends Model implements TranslatableContract
{
    use HasFactory;
    use Translatable;

    /** {@inheritdoc} */
    public $translatedAttributes = ['name', 'description'];

    protected $fillable = [
        'key',
        'icon',
        'color',
        'is_mandatory',
        'default_opt_in',
        'sort_order',
    ];

    protected $casts = [
        'is_mandatory'   => 'boolean',
        'default_opt_in' => 'boolean',
        'sort_order'     => 'integer',
    ];

    public function templates(): HasMany
    {
        return $this->hasMany(MailTemplate::class);
    }

    public function mailings(): HasMany
    {
        return $this->hasMany(Mailing::class);
    }

    public function preferences(): HasMany
    {
        return $this->hasMany(MailPreference::class);
    }

    public function scopeOrdered(Builder $query): Builder
    {
        return $query->orderBy('sort_order')->orderBy('id');
    }

    /**
     * The category the code means when it names one, or null when an
     * install has had it deleted.
     */
    public static function findByKey(string $key): ?self
    {
        return static::where('key', $key)->first();
    }
}
