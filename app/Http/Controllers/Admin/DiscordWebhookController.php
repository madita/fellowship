<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Jobs\SendDiscordWebhook;
use App\Models\DiscordWebhook;
use App\Services\DiscordWebhookService;
use App\Support\DiscordEvents;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Discord channels that announce what happens on the site. The webhook
 * address is a credential: it is written but never read back.
 */
class DiscordWebhookController extends Controller
{
    /**
     * Only a Discord webhook address is accepted.
     */
    private const URL_PATTERN = '#^https://(discord|discordapp)\.com/api/webhooks/\d+/[\w-]+$#';

    public function __construct()
    {
        $this->middleware(['auth:sanctum']);
        $this->middleware(['admin']);
    }

    public function index(): JsonResponse
    {
        return response()->json([
            'data'   => DiscordWebhook::with('creator:id,username')->latest()->get(),
            'events' => DiscordEvents::keys(),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $this->validated($request, true);

        $webhook = DiscordWebhook::create([
            ...$validated,
            'created_by_user_id' => $request->user()->id,
        ]);

        return response()->json($webhook->fresh('creator'), 201);
    }

    public function update(Request $request, DiscordWebhook $discordWebhook): JsonResponse
    {
        $validated = $this->validated($request, false);

        // Left empty, the address stays as it is
        if (blank($validated['url'] ?? null)) {
            unset($validated['url']);
        }

        $discordWebhook->update($validated);

        return response()->json($discordWebhook->fresh('creator'));
    }

    public function destroy(DiscordWebhook $discordWebhook): JsonResponse
    {
        $discordWebhook->delete();

        return response()->json(['message' => __('messages.discord.deleted')]);
    }

    /**
     * Post a message to the channel right now, so the admin can see it arrive.
     */
    public function test(DiscordWebhook $discordWebhook, DiscordWebhookService $service): JsonResponse
    {
        SendDiscordWebhook::dispatchSync($discordWebhook, $service->payload('test', [
            'title'       => __('messages.discord.test_title'),
            'description' => __('messages.discord.test_body', ['app' => config('app.name')]),
            'url'         => config('app.url'),
        ]));

        $discordWebhook->refresh();

        return response()->json([
            'delivered' => $discordWebhook->last_status !== null && $discordWebhook->last_status < 300,
            'webhook'   => $discordWebhook,
        ]);
    }

    private function validated(Request $request, bool $urlRequired): array
    {
        return $request->validate([
            'name'      => ['required', 'string', 'max:100'],
            'url'       => [$urlRequired ? 'required' : 'nullable', 'string', 'regex:' . self::URL_PATTERN],
            'events'    => ['required', 'array', 'min:1'],
            'events.*'  => [Rule::in(DiscordEvents::keys())],
            'is_active' => ['boolean'],
        ], [
            'url.regex' => __('messages.discord.invalid_url'),
        ]);
    }
}
