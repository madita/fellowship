<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class Relateable extends Model
{
    /** @var bool */
    public $incrementing = false;
    /** @var array */
    protected $guarded = [];

    /** @var string|null */
    protected $primaryKey = null;

    public function related(): MorphTo
    {
        return $this->morphTo('related');
    }

    public function source(): MorphTo
    {
        return $this->morphTo('source');
    }

    public function getRelateableValues(): array
    {
        return [
            'type' => $this->related_type,
            'id'   => $this->related_id,
        ];
    }
}
