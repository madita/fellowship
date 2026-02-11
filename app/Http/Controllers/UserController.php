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
            ->whereRaw('LOWER(username) LIKE ?', ['%' . Str::lower($q) . '%'])
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

    /**
     * Update user preferences (timezone, date format, theme, language).
     *
     * @param Request $request
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function updatePreferences(Request $request)
    {
        $validated = $request->validate([
            'timezone'    => 'nullable|string|timezone',
            'date_format' => 'nullable|string|in:Y-m-d,d/m/Y,m/d/Y,d.m.Y',
            'time_format' => 'nullable|string|in:H:i:s,h:i:s A,H:i,h:i A',
            'theme_mode'  => 'nullable|string|in:light,dark,system',
            'language'    => 'nullable|string|in:en,de,es,fr,it,pt,ja,zh',
        ]);

        $user = auth()->user();
        $user->update($validated);

        return response()->json([
            'message' => 'Preferences updated successfully',
            'user'    => $user,
        ]);
    }
}
