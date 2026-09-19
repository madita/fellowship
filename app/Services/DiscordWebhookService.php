<?php

namespace App\Services;

use App\Jobs\SendDiscordWebhook;
use App\Models\DiscordWebhook;
use App\Support\DiscordEvents;
use Illuminate\Support\Str;

/**
 * Announces what happens on the site in Discord channels. Every webhook that
 * subscribed to the event gets one message.
 */
class DiscordWebhookService
{
    /**
     * @param  string  $event  one of DiscordEvents
     * @param  array  $message  title, description, url, author, fields
     */
    public function announce(string $event, array $message): void
    {
        $webhooks = DiscordWebhook::active()->get()->filter(fn (DiscordWebhook $webhook) => $webhook->handles($event));

        foreach ($webhooks as $webhook) {
            SendDiscordWebhook::dispatch($webhook, $this->payload($event, $message));
        }
    }

    /**
     * A Discord message with a single embed.
     */
    public function payload(string $event, array $message): array
    {
        $url = $message['url'] ?? null;

        $embed = array_filter([
            'title'       => Str::limit((string) ($message['title'] ?? ''), 240),
            'description' => isset($message['description'])
                ? Str::limit(trim(html_entity_decode(strip_tags((string) $message['description']))), 600)
                : null,
            'url'         => $url ? $this->absolute($url) : null,
            'color'       => DiscordEvents::color($event),
            'timestamp'   => now()->toIso8601String(),
            'author'      => isset($message['author']) ? ['name' => (string) $message['author']] : null,
            'footer'      => ['text' => config('app.name') . ' · ' . $event],
            'fields'      => $this->fields($message['fields'] ?? []),
        ], fn ($value) => $value !== null && $value !== '' && $value !== []);

        return ['embeds' => [$embed]];
    }

    /**
     * Discord needs the whole address, the site works in relative ones.
     */
    public function absolute(string $url): string
    {
        return Str::startsWith($url, ['http://', 'https://']) ? $url : rtrim(config('app.url'), '/') . '/' . ltrim($url, '/');
    }

    private function fields(array $fields): array
    {
        return collect($fields)
            ->filter(fn ($value) => filled($value))
            ->map(fn ($value, $name) => ['name' => (string) $name, 'value' => Str::limit((string) $value, 200), 'inline' => true])
            ->values()
            ->all();
    }
}
