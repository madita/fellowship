<?php

namespace App\Models\Help;

use Astrotomic\Translatable\Contracts\Translatable as TranslatableContract;
use Astrotomic\Translatable\Translatable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One thing a tour has to say, and what on the page it is about.
 *
 * `target` is a CSS selector. The one worth writing is the `data-help`
 * name a component carries — `[data-help="toolbar-notifications"]` —
 * because it survives a redesign where a class name does not. Left empty,
 * the step is about the page as a whole and is shown in the middle with
 * nothing singled out.
 */
class HelpTourStep extends Model implements TranslatableContract
{
    use HasFactory;
    use Translatable;

    public const PLACEMENTS = ['auto', 'top', 'bottom', 'left', 'right'];

    /** {@inheritdoc} */
    public $translatedAttributes = ['title', 'content'];

    protected $fillable = [
        'help_tour_id',
        'target',
        'placement',
        'sort_order',
    ];

    protected $casts = [
        'sort_order' => 'integer',
    ];

    public function tour(): BelongsTo
    {
        return $this->belongsTo(HelpTour::class, 'help_tour_id');
    }
}
