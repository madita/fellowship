<?php

namespace App\Models;

use App\Models\Chat\Message;
use App\Models\Conversation\Conversation;
use App\Models\Event\Event;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;
use Spatie\MediaLibrary\MediaCollections\Models\Media;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable implements HasMedia, MustVerifyEmail
{
    use HasApiTokens;
    use HasFactory;
    use Notifiable;
    use HasRoles;
    use InteractsWithMedia;

    protected $guard_name = 'api';

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'name',
        'username',
        'email',
        'password',
        'last_login_at',
        'last_login_ip',
        'timezone',
        'date_format',
        'time_format',
    ];

    /**
     * The accessors to append to the model's array form.
     *
     * @var array
     */
    protected $appends = [
        'avatar',
        'isAdmin',
        'initials',
        'timezone',
        'date_format',
        'time_format',
    ];

    /**
     * The attributes that should be hidden for arrays.
     *
     * @var array
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * The attributes that should be cast to native types.
     *
     * @var array
     */
    protected $casts = [
        'email_verified_at' => 'datetime',
    ];

    /**
     * Determine if the user is an administrator.
     *
     * @return bool
     */
    public function isAdmin()
    {
        return $this->hasRole('admin');
    }

    /**
     * Determine if the user is an administrator.
     *
     * @return bool
     */
    public function getIsAdminAttribute()
    {
        return $this->isAdmin();
    }

    public function getInitals()
    {
        if ($this->name) {
            $initials = explode(' ', strtoupper($this->name));
            $initial = substr($initials[0], 0, 1);
            if (count($initials) > 1) {
                $initial .= substr($initials[count($initials) - 1], 0, 1);
            }

            return $initial;
        }

        return strtoupper(substr($this->username, 0, 1));
    }

    public function getInitialsAttribute()
    {
        return $this->getInitals();
    }

    public function getAvatar()
    {
        if (!count($this->getMedia('avatars'))) {
            return '';
        }

        return $this->getFirstMediaUrl('avatars');
    }

    public function getAvatarAttribute()
    {
        return $this->getAvatar();
    }

    /**
     * Get the user's timezone preference with fallback to global setting
     *
     * @return string
     */
    public function getTimezoneAttribute()
    {
        // Get the raw database value
        $value = $this->getAttributeFromArray('timezone');

        // Return user's preference if set
        if ($value !== null && $value !== '') {
            return $value;
        }

        // Try to get from global settings, with fallback to UTC
        try {
            return Setting::get('default_timezone', 'UTC');
        } catch (\Exception $e) {
            return 'UTC';
        }
    }

    /**
     * Get the user's date format preference with fallback to global setting
     *
     * @return string
     */
    public function getDateFormatAttribute()
    {
        // Get the raw database value
        $value = $this->getAttributeFromArray('date_format');

        // Return user's preference if set
        if ($value !== null && $value !== '') {
            return $value;
        }

        // Try to get from global settings, with fallback to Y-m-d
        try {
            return Setting::get('date_format', 'Y-m-d');
        } catch (\Exception $e) {
            return 'Y-m-d';
        }
    }

    /**
     * Get the user's time format preference with fallback to global setting
     *
     * @return string
     */
    public function getTimeFormatAttribute()
    {
        // Get the raw database value
        $value = $this->getAttributeFromArray('time_format');

        // Return user's preference if set
        if ($value !== null && $value !== '') {
            return $value;
        }

        // Try to get from global settings, with fallback to H:i:s
        try {
            return Setting::get('time_format', 'H:i:s');
        } catch (\Exception $e) {
            return 'H:i:s';
        }
    }

    public function registerMediaConversions(?Media $media = null): void
    {
        $this->addMediaConversion('thumb')
            ->width(100)
            ->height(100);
    }

    public function receivesBroadcastNotificationsOn()
    {
        return 'users.'.$this->id;
    }

    public function pages()
    {
        return $this->hasMany(Page::class);
    }

    public function posts()
    {
        return $this->hasMany(Post::class);
    }

    public function messages()
    {
        return $this->hasMany(Message::class);
    }

    public function events()
    {
        return $this->hasMany(Event::class);
    }

    public function eventGuest()
    {
        return $this->belongsToMany(Event::class, 'event_guests')
            ->withPivot('type')
            ->withTimestamps();
    }

    public function conversations()
    {
//        return $this->belongsToMany(Conversation::class)->whereNull('parent_id')->orderBy('last_reply', 'desc');
        return $this->belongsToMany(Conversation::class)->withPivot('read_at');
    }

    public function inConversation($id)
    {
        return $this->conversations->contains('id', $id);
    }

    public function hasRead(Conversation $conversation)
    {
        return $this->conversations->find($conversation->id)->pivot->read_at;
    }
}
