<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\UserProfile;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

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
        $q = $request->input('query', '');

        $query = User::where('id', '!=', auth()->id());

        if ( ! empty($q)) {
            $search = '%' . Str::lower($q) . '%';
            $query->where(function ($sub) use ($search) {
                $sub->whereRaw('LOWER(username) LIKE ?', [$search])
                    ->orWhereRaw('LOWER(name) LIKE ?', [$search])
                    ->orWhereRaw('LOWER(email) LIKE ?', [$search]);
            });
        }

        return $query->get()->map(function ($user) {
            return [
                'id'       => $user->id,
                'username' => $user->username,
                'name'     => $user->name,
                'email'    => $user->email,
                'avatar'   => $user->avatar,
                'initials' => $user->initials,
            ];
        });
    }

    /**
     * Update the signed-in member's own name, username and e-mail. The users
     * table is for admins editing somebody else.
     */
    public function updateProfile(Request $request)
    {
        $user = auth()->user();

        $validated = $request->validate([
            'name'     => 'required|string|max:255',
            'username' => ['required', 'string', 'max:255', Rule::unique('users', 'username')->ignore($user->id)],
            'email'    => ['required', 'email', Rule::unique('users', 'email')->ignore($user->id)],
        ]);

        $user->update($validated);

        return response()->json([
            'message' => __('messages.user.profile_updated'),
            'user'    => $user,
        ]);
    }

    /**
     * The rest of what the member has told us about themselves, as they see
     * it — including the parts they keep to themselves.
     */
    public function information()
    {
        return response()->json([
            'data'      => auth()->user()->profileOrNew()->ownShape(),
            // So the form knows which fields can be shown to others at all
            'shareable' => array_keys(UserProfile::SHAREABLE),
        ]);
    }

    /**
     * Save it. Everything is optional — a member owes us none of this.
     */
    public function updateInformation(Request $request)
    {
        $user = auth()->user();

        $validated = $request->validate([
            'bio'           => ['nullable', 'string', 'max:1000'],
            'pronouns'      => ['nullable', 'string', 'max:40'],
            'city'          => ['nullable', 'string', 'max:120'],
            'country'       => ['nullable', 'string', 'max:120'],
            'website'       => ['nullable', 'url', 'max:255'],
            'birthday'      => ['nullable', 'date', 'before:today'],
            'socials'       => ['nullable', 'array'],
            'socials.*'     => ['nullable', 'string', 'max:120'],
            'phone'         => ['nullable', 'string', 'max:40'],
            'address_line1' => ['nullable', 'string', 'max:255'],
            'address_line2' => ['nullable', 'string', 'max:255'],
            'postcode'      => ['nullable', 'string', 'max:20'],
            'state'         => ['nullable', 'string', 'max:120'],
            'visibility'    => ['nullable', 'array'],
            'visibility.*'  => ['boolean'],
        ]);

        // Only the fields that can be shown at all are worth remembering a
        // choice for; anything else in the payload is dropped.
        $visibility = collect($validated['visibility'] ?? [])
            ->only(array_keys(UserProfile::SHAREABLE))
            ->map(fn ($shown) => (bool) $shown)
            ->all();

        $profile = $user->profile()->firstOrNew();
        $profile->fill(array_merge($validated, ['visibility' => $visibility]));
        $user->profile()->save($profile);

        return response()->json([
            'message' => __('messages.user.profile_updated'),
            'data'    => $profile->fresh()->ownShape(),
        ]);
    }

    /**
     * Update user preferences (timezone, date format, theme, language).
     *
     *
     * @return JsonResponse
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
            'message' => __('messages.user.preferences_updated'),
            'user'    => $user,
        ]);
    }
}
