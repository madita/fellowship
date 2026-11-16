<?php

namespace App\Services;

use App\Jobs\SendDiscordWebhook;
use App\Models\DiscordWebhook;
use App\Support\DiscordEvents;
use Illuminate\Support\Facades\Lang;
use Illuminate\Support\Str;

/**
 * Announces what happens on the site in Discord channels. Every webhook that
 * subscribed to the event gets one message, written in the language of that
 * channel — not in the language of whoever happened to trigger it.
 *
 * A message is given as a spec: plain strings are data (titles, names),
 * arrays are translated, as ['messages.discord.event_joined', ['name' => …]].
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
            SendDiscordWebhook::dispatch($webhook, $this->payload($event, $message, $webhook->messageLocale()));
        }
    }

    /**
     * A Discord message with a single embed, in the given language.
     */
    public function payload(string $event, array $message, ?string $locale = null): array
    {
        $locale ??= config('app.locale');
        $url = $message['url'] ?? null;

        $embed = array_filter([
            'title'       => Str::limit($this->text($message['title'] ?? '', $locale), 240),
            'description' => isset($message['description'])
                ? Str::limit(trim(html_entity_decode(strip_tags($this->text($message['description'], $locale)))), 600)
                : null,
            'url'         => $url ? $this->absolute($url) : null,
            'color'       => DiscordEvents::color($event),
            'timestamp'   => now()->toIso8601String(),
            'author'      => isset($message['author']) ? ['name' => $this->text($message['author'], $locale)] : null,
            'footer'      => ['text' => config('app.name') . ' · ' . $this->eventLabel($event, $locale)],
            'fields'      => $this->fields($message['fields'] ?? [], $locale),
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

    /**
     * Data as it is; ['key', ['replace' => …]] translated into the channel's language.
     */
    private function text(mixed $value, string $locale): string
    {
        if (is_array($value)) {
            return (string) Lang::get($value[0], $value[1] ?? [], $locale);
        }

        return (string) $value;
    }

    /**
     * Field names are translation keys, their values are data.
     */
    private function fields(array $fields, string $locale): array
    {
        return collect($fields)
            ->filter(fn ($value) => filled($value))
            ->map(fn ($value, $name) => [
                'name'   => Lang::has($name, $locale) ? (string) Lang::get($name, [], $locale) : (string) $name,
                'value'  => Str::limit($this->text($value, $locale), 200),
                'inline' => true,
            ])
            ->values()
            ->all();
    }

    private function eventLabel(string $event, string $locale): string
    {
        $key = "messages.discord.events.{$event}";

        return Lang::has($key, $locale) ? (string) Lang::get($key, [], $locale) : $event;
    }
}
