<?php

namespace App\Http\Controllers;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use App\Models\User;

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

    public function searchUsers(Request $request) {
       // dd($request->all());
       /* $query = request()->query('query');
        $users = User::where('username', 'like', "%{$query}%")
            ->orWhere('email', 'like', "%{$query}%")
            ->get(['id', 'username']);

        return response()->json($users);*/

        if (!$q = $request->get('query', '')) {
            //return response()->json([]);
            return User::all()
                ->get(['id', 'username']);
        }

        return User::where(DB::raw('LOWER(username)'), 'LIKE', '%' . Str::lower($q) . '%')
            ->get(['id', 'username']);
    }
}
