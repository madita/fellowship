<?php

namespace App\Jobs;

use App\Models\DiscordWebhook;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Posts one message to a Discord channel. Whether it worked is kept on the
 * webhook, so the admin settings can show what happened last.
 */
class SendDiscordWebhook implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public int $timeout = 15;

    public function __construct(
        public DiscordWebhook $webhook,
        public array $payload
    ) {
    }

    public function backoff(): array
    {
        return [5, 30];
    }

    public function handle(): void
    {
        try {
            $response = Http::timeout(8)->asJson()->post($this->webhook->url, $this->payload);

            $this->record($response->status(), $response->successful() ? null : Str::limit($response->body(), 190));

            // Discord asks to slow down, or is briefly unavailable: try again
            if ($response->status() === 429 || $response->serverError()) {
                $this->release(30);
            }
        } catch (\Throwable $e) {
            $this->record(null, Str::limit($e->getMessage(), 190));
            Log::warning('Discord webhook failed', ['webhook' => $this->webhook->id, 'message' => $e->getMessage()]);

            throw $e;
        }
    }

    public function failed(\Throwable $e): void
    {
        $this->record(null, Str::limit($e->getMessage(), 190));
    }

    private function record(?int $status, ?string $error): void
    {
        $this->webhook->forceFill([
            'last_sent_at' => now(),
            'last_status'  => $status,
            'last_error'   => $error,
        ])->saveQuietly();
    }
}
