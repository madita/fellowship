<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Irc\IrcComicCharacter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

/**
 * The comic chat character creator: admins build characters out of parts and
 * members pick one for the IRC client.
 */
class IrcComicCharacterController extends Controller
{
    /**
     * Every character, with the parts they can be built from.
     */
    public function index(): JsonResponse
    {
        return response()->json([
            'data'  => IrcComicCharacter::inOrder()->get(),
            'parts' => IrcComicCharacter::PARTS,
            'tones' => IrcComicCharacter::TONES,
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate(array_merge([
            'name'       => ['required', 'string', 'max:40'],
            'is_enabled' => ['nullable', 'boolean'],
        ], IrcComicCharacter::specRules()));

        $character = IrcComicCharacter::create([
            'key'        => $this->uniqueKey($data['name']),
            'name'       => $data['name'],
            'spec'       => $data['spec'],
            'is_enabled' => $data['is_enabled'] ?? true,
            'sort_order' => (int) IrcComicCharacter::max('sort_order') + 1,
        ]);

        return response()->json(['data' => $character], 201);
    }

    public function update(Request $request, IrcComicCharacter $character): JsonResponse
    {
        $data = $request->validate(array_merge([
            'name'       => ['required', 'string', 'max:40'],
            'is_enabled' => ['nullable', 'boolean'],
        ], IrcComicCharacter::specRules()));

        $character->update([
            'name'       => $data['name'],
            'spec'       => $data['spec'],
            'is_enabled' => $data['is_enabled'] ?? $character->is_enabled,
        ]);

        return response()->json(['data' => $character->fresh()]);
    }

    /**
     * Only a character an admin built can be deleted — the ones that ship
     * with the site can be switched off, so nobody who picked one is left
     * without a character.
     */
    public function destroy(IrcComicCharacter $character): JsonResponse
    {
        if ($character->is_builtin) {
            return response()->json([
                'message' => __('messages.irc.builtin_character'),
            ], 422);
        }

        $character->delete();

        return response()->json(['message' => __('messages.irc.character_deleted')]);
    }

    /**
     * A key derived from the name, kept unique so a character can be
     * addressed by something readable.
     */
    private function uniqueKey(string $name): string
    {
        $base = Str::slug($name) ?: 'character';
        $key  = $base;
        $next = 2;

        while (IrcComicCharacter::where('key', $key)->exists()) {
            $key = "{$base}-{$next}";
            $next++;
        }

        return $key;
    }
}
