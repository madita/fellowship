<?php

namespace App\Http\Controllers;

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
            'avatar' => ['required', 'image']
        ]);

        $user = auth()->user();
        $user->addMediaFromRequest('avatar')->toMediaCollection('avatars');

        return response([], 204);
    }
}
