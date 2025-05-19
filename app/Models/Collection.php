<?php

namespace App\Models;

use App\Models\Tag\Taxonomy;
use Cviebrock\EloquentSluggable\Sluggable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

class Collection extends Model implements HasMedia
{
    use HasFactory;
    use InteractsWithMedia;
    use Sluggable;

    public function sluggable(): array
    {
        return [
            'slug' => [
                'source' => 'name',
            ],
        ];
    }

    protected $fillable = ['name', 'taxonomy_id', 'user_id'];

    public function taxonomy()
    {
        return $this->belongsTo(Taxonomy::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function getCoverImageAttribute()
    {
        // Get the media item marked as the cover
        $media = $this->getMedia('images')->first(fn ($item) => $item->getCustomProperty('is_cover', false));

        // Fallback to the first media item if no cover is explicitly set
        if (!$media) {
            $media = $this->getFirstMedia('images');
        }

        return $media ? $media->getUrl() : null;
    }

    public function registerMediaConversions(?Media $media = null): void
    {
        $this->addMediaConversion('thumb')
            ->width(368)
            ->height(232)
            ->sharpen(10);
    }
}
