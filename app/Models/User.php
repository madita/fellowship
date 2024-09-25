<?php

namespace App\Models;

use App\Models\Chat\Message;
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

    public function registerMediaConversions(Media $media = null): void
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
}
