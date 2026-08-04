<?php

namespace App\Services;

use App\Models\Setting;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class NewsletterService
{
    protected $enabled;

    protected $provider;

    protected $apiKey;

    protected $listId;

    public function __construct()
    {
        $this->loadSettings();
    }

    /**
     * Load newsletter settings from database.
     */
    protected function loadSettings()
    {
        $this->enabled = Setting::where('key', 'newsletter_enabled')->value('value') === 'true';
        $this->provider = Setting::where('key', 'newsletter_provider')->value('value');
        $this->apiKey = Setting::where('key', 'newsletter_api_key')->value('value');
        $this->listId = Setting::where('key', 'newsletter_list_id')->value('value');
    }

    /**
     * Subscribe an email to the newsletter.
     */
    public function subscribe(string $email): array
    {
        if (! $this->enabled) {
            return [
                'success' => false,
                'message' => __('messages.newsletter.disabled'),
            ];
        }

        if (! $this->provider || ! $this->apiKey || ! $this->listId) {
            Log::warning('Newsletter settings incomplete', [
                'provider' => $this->provider,
                'has_api_key' => ! empty($this->apiKey),
                'has_list_id' => ! empty($this->listId),
            ]);

            return [
                'success' => false,
                'message' => __('messages.newsletter.not_configured'),
            ];
        }

        try {
            switch ($this->provider) {
                case 'mailchimp':
                    return $this->subscribeMailchimp($email);
                case 'mailerlite':
                    return $this->subscribeMailerlite($email);
                case 'sendgrid':
                    return $this->subscribeSendgrid($email);
                case 'convertkit':
                    return $this->subscribeConvertkit($email);
                case 'activecampaign':
                    return $this->subscribeActiveCampaign($email);
                case 'sendinblue':
                    return $this->subscribeSendinblue($email);
                default:
                    return [
                        'success' => false,
                        'message' => __('messages.newsletter.unsupported'),
                    ];
            }
        } catch (\Exception $e) {
            Log::error('Newsletter subscription failed', [
                'provider' => $this->provider,
                'email' => $email,
                'error' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'message' => __('messages.newsletter.error_occurred'),
            ];
        }
    }

    /**
     * Subscribe via Mailchimp.
     */
    protected function subscribeMailchimp(string $email): array
    {
        // Extract datacenter from API key (e.g., us19 from key-us19)
        $datacenter = substr($this->apiKey, strpos($this->apiKey, '-') + 1);
        $url = "https://{$datacenter}.api.mailchimp.com/3.0/lists/{$this->listId}/members";

        $response = Http::withBasicAuth('anystring', $this->apiKey)
            ->post($url, [
                'email_address' => $email,
                'status' => 'subscribed',
            ]);

        if ($response->successful() || $response->status() === 400 && str_contains($response->json('title', ''), 'Member Exists')) {
            return [
                'success' => true,
                'message' => __('messages.newsletter.subscribed'),
            ];
        }

        Log::error('Mailchimp subscription failed', [
            'status' => $response->status(),
            'response' => $response->json(),
        ]);

        return [
            'success' => false,
            'message' => __('messages.newsletter.try_again'),
        ];
    }

    /**
     * Subscribe via MailerLite.
     */
    protected function subscribeMailerlite(string $email): array
    {
        $url = "https://api.mailerlite.com/api/v2/groups/{$this->listId}/subscribers";

        $response = Http::withHeaders([
            'X-MailerLite-ApiKey' => $this->apiKey,
        ])->post($url, [
            'email' => $email,
        ]);

        if ($response->successful() || $response->status() === 400 && str_contains($response->json('error.message', ''), 'already exists')) {
            return [
                'success' => true,
                'message' => __('messages.newsletter.subscribed'),
            ];
        }

        return [
            'success' => false,
            'message' => __('messages.newsletter.try_again'),
        ];
    }

    /**
     * Subscribe via SendGrid.
     */
    protected function subscribeSendgrid(string $email): array
    {
        $url = 'https://api.sendgrid.com/v3/marketing/contacts';

        $response = Http::withHeaders([
            'Authorization' => 'Bearer '.$this->apiKey,
        ])->put($url, [
            'list_ids' => [$this->listId],
            'contacts' => [
                ['email' => $email],
            ],
        ]);

        if ($response->successful()) {
            return [
                'success' => true,
                'message' => __('messages.newsletter.subscribed'),
            ];
        }

        return [
            'success' => false,
            'message' => __('messages.newsletter.try_again'),
        ];
    }

    /**
     * Subscribe via ConvertKit.
     */
    protected function subscribeConvertkit(string $email): array
    {
        $url = "https://api.convertkit.com/v3/forms/{$this->listId}/subscribe";

        $response = Http::post($url, [
            'api_key' => $this->apiKey,
            'email' => $email,
        ]);

        if ($response->successful()) {
            return [
                'success' => true,
                'message' => __('messages.newsletter.subscribed'),
            ];
        }

        return [
            'success' => false,
            'message' => __('messages.newsletter.try_again'),
        ];
    }

    /**
     * Subscribe via ActiveCampaign.
     */
    protected function subscribeActiveCampaign(string $email): array
    {
        // ActiveCampaign requires account URL in settings
        // For now, return not implemented
        return [
            'success' => false,
            'message' => __('messages.newsletter.coming_soon'),
        ];
    }

    /**
     * Subscribe via Sendinblue (Brevo).
     */
    protected function subscribeSendinblue(string $email): array
    {
        $url = 'https://api.sendinblue.com/v3/contacts';

        $response = Http::withHeaders([
            'api-key' => $this->apiKey,
        ])->post($url, [
            'email' => $email,
            'listIds' => [(int) $this->listId],
            'updateEnabled' => true,
        ]);

        if ($response->successful() || $response->status() === 400 && str_contains($response->json('message', ''), 'already exists')) {
            return [
                'success' => true,
                'message' => __('messages.newsletter.subscribed'),
            ];
        }

        return [
            'success' => false,
            'message' => __('messages.newsletter.try_again'),
        ];
    }
}
