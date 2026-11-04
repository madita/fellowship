<?php

namespace App\Models\Mail;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One member's answer about one kind of mail.
 *
 * Only answers that differ from the category default need a row, which is
 * why a member who has never opened the page has none at all. A category
 * added next year therefore reaches whoever its own default says it
 * should, instead of nobody.
 */
class MailPreference extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'mail_category_id',
        'opted_in',
    ];

    protected $casts = [
        'opted_in' => 'boolean',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(MailCategory::class, 'mail_category_id');
    }
}
