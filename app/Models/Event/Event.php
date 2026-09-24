<?php

namespace App\Models\Event;

use App\Services\DiscordWebhookService;
use App\Support\DiscordEvents;
use App\Traits\HasRelateableContent;
use App\Traits\NotifiesMentions;
use Astrotomic\Translatable\Contracts\Translatable as TranslatableContract;
use Astrotomic\Translatable\Translatable;
use Carbon\Carbon;
use Cviebrock\EloquentSluggable\Sluggable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Event extends Model implements TranslatableContract
{
    use HasRelateableContent;
    use NotifiesMentions;
    use Sluggable;
    use SoftDeletes;
    use Translatable;

    public $translatedAttributes = ['title', 'description'];

    protected array $mentionFields = ['description'];

    protected $table = 'events';

    protected $fillable = [
        'user_id',
        'image',
        'startTime',
        'endTime',
        'startDate',
        'endDate',
        'event_type_id',
    ];

    /**
     * When the event is over.
     *
     * An event with no end date ends the day it starts, and one with no
     * end time runs to the end of that day — the same reading the show
     * endpoint uses when it composes `end`.
     */
    public function endsAt(): ?Carbon
    {
        $date = $this->endDate ?: $this->startDate;

        if (! $date) {
            return null;
        }

        $time = $this->endDate ? $this->endTime : ($this->endTime ?: $this->startTime);

        return Carbon::parse(
            Carbon::parse($date)->format('Y-m-d') . ' ' . ($time ?: '23:59:59')
        );
    }

    // An event nobody can still attend, because it has already happened.
    public function hasEnded(): bool
    {
        $endsAt = $this->endsAt();

        return $endsAt !== null && $endsAt->isPast();
    }

    public function sluggable(): array
    {
        return [
            'slug' => [
                'source' => 'title',
            ],
        ];
    }

    public function details()
    {
        return $this->hasOne("App\\Models\\Event\EventDetail");
    }

    public function type()
    {
        return $this->hasOne("App\\Models\\Event\EventType", 'event_type_id');
    }

    public function user()
    {
        return $this->belongsTo("App\\Models\User");
    }

    //    public function posts(){
    //        return $this->hasMany("App\\Models\\Post")->orderBy("created_at", "desc")->with("comments")->withTrashed();
    //    }

    public function getImage()
    {
        if ($this->image) {
            return '/uploads/' . $this->image;
        } else {
            return 'img/cover.jpeg';
        }
    }

    //    public function hasImages(){
    //        $posts = Post::where("event_id", "=", $this->id)->whereNotNull("image")->get();
    //
    //        return count($posts);
    //    }

    //    public function getImages(){
    //        $posts = Post::where("event_id", "=", $this->id)->orderBy("created_at", "desc")->take(2)->get();
    //        return $posts;
    //    }

    //    public function countImages(){
    //        $posts = Post::where("event_id", "=", $this->id)->get();
    //        $images = array();
    //
    //        foreach($posts as $post){
    //            array_push($images, $post->getImage());
    //        }
    //
    //        return count(array_filter($images));
    //    }

    //    public function going()
    //    {
    //        return $this->belongsToMany('App\\Models\\User', 'event_guests')->wherePivot('type', '=', 'going');
    //    }
    //
    //    public function notgoing()
    //    {
    //        return $this->belongsToMany('App\\Models\\User', 'event_guests')->wherePivot('type', '=', 'notgoing');
    //    }
    //
    //    public function maybegoing()
    //    {
    //        return $this->belongsToMany('App\\Models\\User', 'event_guests')->wherePivot('type', '=', 'maybe');
    //    }

    public function answer($answer)
    {
        return $this->belongsToMany('App\\Models\\User', 'event_guests')->wherePivot('type', '=', $answer)->withPivot('approved_at');
    }

    //    public function categories(){
    //        return $this->belongsToMany("App\\Models\\Category");
    //    }

    public function allUsers()
    {
        return $this->belongsToMany('App\\Models\\User', 'event_guests')
            ->withPivot('type')
            ->withTimestamps();
    }

    // ── @mentions in the event description ──────────────────────────

    public function mentionContext(): string
    {
        return 'event';
    }

    public function mentionTitle(): string
    {
        return (string) $this->title;
    }

    public function mentionUrl(): string
    {
        return "/events/{$this->id}";
    }

    protected static function booted(): void
    {
        static::created(function (Event $event): void {
            app(DiscordWebhookService::class)->announce(DiscordEvents::EVENT_CREATED, [
                'title'       => $event->title,
                'description' => $event->description,
                'url'         => "/events/{$event->id}",
                'fields'      => ['messages.discord.fields.starts' => $event->startDate ? (string) $event->startDate : null],
            ]);
        });
    }
}
