<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class UserController extends Controller
{
    /**
     * Create a new controller instance.
     *
     * @return void
     */
    public function __construct()
    {
        $this->middleware('auth');
    }

    public function uploadAvatar()
    {
        request()->validate([
            'avatar' => ['required', 'image'],
        ]);

        $user = auth()->user();
        $user->addMediaFromRequest('avatar')->toMediaCollection('avatars');

        return response([], 204);
    }

    public function searchUsers(Request $request)
    {
        $q = $request->get('query', '');

        if (empty($q)) {
            // Return all users with avatar
            return User::where('id', '!=', auth()->id())
                ->get(['id', 'username', 'email'])
                ->map(function ($user) {
                    return [
                        'id'       => $user->id,
                        'username' => $user->username,
                        'email'    => $user->email,
                        'avatar'   => $user->avatar,
                        'initials' => $user->initials,
                    ];
                });
        }

        return User::where('id', '!=', auth()->id())
            ->where(DB::raw('LOWER(username)'), 'LIKE', '%'.Str::lower($q).'%')
            ->get(['id', 'username', 'email'])
            ->map(function ($user) {
                return [
                    'id'       => $user->id,
                    'username' => $user->username,
                    'email'    => $user->email,
                    'avatar'   => $user->avatar,
                    'initials' => $user->initials,
                ];
            });
    }
}
