<?php

namespace App\Models;


use Illuminate\Database\Eloquent\Model;

class Wiki extends Model
{

    protected $table = 'wikiables';
//    protected $guard_name = 'api';

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'title', 'slug', 'status',
    ];
//
//    public function wikiable() {
//        return $this->morphTo();
//    }


}
