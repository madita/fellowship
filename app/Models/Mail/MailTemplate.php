<?php

namespace App\Models\Mail;

use Astrotomic\Translatable\Contracts\Translatable as TranslatableContract;
use Astrotomic\Translatable\Translatable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * The wording of a mail that goes out more than once.
 *
 * Subject and body are translated, so a member reads the mail in the
 * language they chose, and both carry {{ placeholders }} filled in per
 * recipient. The body is cleaned HTML, the same as a forum post - a
 * template is edited from the browser, so it is never treated as code.
 *
 * `is_system` marks a template the code sends by key. An admin may
 * rewrite every word of it, and move it to another category; they may not
 * rename or delete it, because the send would then find nothing.
 */
class MailTemplate extends Model implements TranslatableContract
{
    use HasFactory;
    use Translatable;

    /** {@inheritdoc} */
    public $translatedAttributes = ['subject', 'body'];

    protected $fillable = [
        'key',
        'mail_category_id',
        'is_system',
        'variables',
        'action_label',
        'action_url',
        'is_enabled',
    ];

    protected $casts = [
        'variables'  => 'array',
        'is_system'  => 'boolean',
        'is_enabled' => 'boolean',
    ];

    public function category(): BelongsTo
    {
        return $this->belongsTo(MailCategory::class, 'mail_category_id');
    }

    /**
     * The template the code means when it names one. Null when an install
     * has deleted it, or switched it off - a caller that gets null sends
     * nothing, which is the point of the switch.
     */
    public static function active(string $key): ?self
    {
        return static::with('category')
            ->where('key', $key)
            ->where('is_enabled', true)
            ->first();
    }
}
