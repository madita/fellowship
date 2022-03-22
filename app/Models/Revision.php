<?php

namespace App\Models;

use Carbon\Carbon;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Arr;

class Revision extends Model
{

    /**
     * The database table used by the model.
     *
     * @var string
     */
    protected static $customTable;

    /**
     * Allow mass assignement.
     *
     * @var array
     */
    protected $fillable = [
        'revisionable_type', 'action', 'user_id', 'old_value',
        'new_value', 'ip', 'ip_forwarded', 'created_at',
    ];

    public $timestamps = false;

    protected $dates = ['created_at'];

    /**
     * {@inheritdoc}
     */
    public static function boot()
    {
        parent::boot();

        // Make it read-only
        static::updating(function () {
            return false;
        });
    }

    /**
     * Revision belongs to User (action Executor).
     *
     * @link https://laravel.com/docs/eloquent-relationships#one-to-one
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function executor()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /**
     * Revision morphs to models in revisioned_type.
     *
     * @link https://laravel.com/docs/eloquent-relationships#polymorphic-relations
     *
     * @return \Illuminate\Database\Eloquent\Relations\MorphTo
     */
    public function revisioned()
    {
        // For BC we use revisionable_id rather than revisionable_id
        return $this->morphTo('revisioned', 'revisionable_type', 'revisionable_id');
    }

    /**
     * Get array of updated fields.
     *
     * @return array
     */
    public function getUpdated()
    {
        return array_keys(array_diff_assoc($this->new_value, $this->old_value));
    }

    /**
     * Get diff of the old/new arrays.
     *
     * @return array
     */
    public function getDiff()
    {
        $diff = [];

        foreach ($this->getUpdated() as $key) {
            $diff[$key]['old_value'] = $this->old_value($key);

            $diff[$key]['new_value'] = $this->new_value($key);
        }

        return $diff;
    }

    /**
     * Determine whether field was updated during current action.
     *
     * @param string $key
     *
     * @return bool
     */
    public function isUpdated($key)
    {
        return in_array($key, $this->getUpdated());
    }

    /**
     * Accessor for old property.
     *
     * @return array
     */
    public function getOldValueAttribute($old)
    {
        return (array) json_decode($old);
    }

    /**
     * Accessor for new property.
     *
     * @return array
     */
    public function getNewValueAttribute($new)
    {
        return (array) json_decode($new);
    }

    /**
     * Get single value from the new/old array.
     *
     * @param string $version
     * @param string $key
     *
     * @return string
     */
    protected function getFromArray($version, $key)
    {
        return Arr::get($this->{$version}, $key);
    }

    /**
     * {@inheritdoc}
     */
    public function getTable()
    {
        $table = $this->table ?: static::$customTable;

        return ($table) ?: parent::getTable();
    }

    /**
     * Set custom table name for the model.
     *
     * @param string $table
     */
    public static function setCustomTable($table)
    {
        if (!isset(static::$customTable)) {
            static::$customTable = $table;
        }
    }


    /**
     * Query scope ordered.
     *
     * @link https://laravel.com/docs/eloquent#local-scopes
     *
     * @param  \Illuminate\Database\Eloquent\Builder
     *
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeOrdered($query)
    {
        return $query->latest()->latest('id');
    }

    /**
     * Query scope for.
     *
     * @link https://laravel.com/docs/eloquent#local-scopes
     *
     * @param \Illuminate\Database\Eloquent\Builder      $query
     * @param \Illuminate\Database\Eloquent\Model|string $table
     *
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeFor($query, $table)
    {
        if ($table instanceof Model) {
            $table = $table->getTable();
        }

        return $query->where('table_name', $table);
    }

    /**
     * Handle dynamic method calls.
     *
     * @param string $method
     * @param array  $parameters
     *
     * @return mixed
     */
    public function __call($method, $parameters)
    {
        if (in_array($method, ['new_value', 'old_value'])) {
            array_unshift($parameters, $method);

            return call_user_func_array([$this, 'getFromArray'], $parameters);
        }

        if ($method == 'label') {
            return reset($parameters);
        }

        return parent::__call($method, $parameters);
    }
}
