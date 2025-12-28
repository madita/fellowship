<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Setting;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;

class SettingsController extends Controller
{
    public function __construct()
    {
        $this->middleware(['auth'])->except(['public']);
        // TODO: Add admin middleware when ready
        // $this->middleware(['auth', 'admin']);
    }

    /**
     * Get public settings (no authentication required)
     */
    public function public(): JsonResponse
    {
        $publicKeys = [
            // General
            'app_name',
            'app_logo',
            'app_copyright',
            'site_tagline',
            'site_url',
            'contact_address',
            'contact_phone',
            'contact_email',
            'social_twitter',
            'social_facebook',
            'social_instagram',
            'maintenance_mode',
            'maintenance_message',

            // Localization
            'default_language',
            'default_timezone',
            'date_format',
            'time_format',
            'available_languages',
            'language_change_enabled',
            'currency',
            'currency_symbol',
            'currency_symbol_position',
            'rtl_support',

            // Branding
            'logo_light',
            'logo_dark',
            'favicon',
            'app_icon',
            'primary_color',
            'secondary_color',
            'font_family',
            'custom_css',
            'theme_mode',
            'login_branding_enabled',

            // SEO
            'meta_title',
            'meta_description',
            'meta_keywords',
            'og_title',
            'og_description',
            'og_image',
            'twitter_card_type',
            'twitter_site',

            // Analytics
            'google_analytics_id',
            'google_tag_manager_id',
            'cookie_consent_enabled',
            'cookie_categories',

            // Legal
            'privacy_policy_url',
            'terms_conditions_url',
            'cookie_policy_url',
            'gdpr_consent_text',

            // Footer
            'custom_footer_enabled',
            'custom_footer_html',
        ];

        $settings = [];

        foreach ($publicKeys as $key) {
            $value = Setting::get($key);
            if ($value !== null) {
                $settings[$key] = $value;
            }
        }

        return response()->json([
            'settings' => $settings,
        ]);
    }

    /**
     * Get all settings
     */
    public function index(): JsonResponse
    {
        $settings = Setting::getAllSettings();

        return response()->json([
            'settings' => $settings,
        ]);
    }

    /**
     * Update settings
     */
    public function update(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'settings' => 'required|array',
            'settings.*.key' => 'required|string',
            'settings.*.value' => 'nullable',
            'settings.*.type' => 'sometimes|string|in:string,boolean,integer,float,file',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        foreach ($request->input('settings', []) as $setting) {
            $type = $setting['type'] ?? 'string';
            $value = $setting['value'];

            // Handle array/json values
            if (is_array($value)) {
                $value = json_encode($value);
                $type = 'json';
            }

            Setting::set($setting['key'], $value, $type);
        }

        Setting::clearCache();

        return response()->json([
            'message' => 'Settings updated successfully',
            'settings' => Setting::getAllSettings(),
        ]);
    }

    /**
     * Upload logo
     */
    public function uploadLogo(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'logo' => 'required|image|mimes:jpeg,png,jpg,gif,svg|max:2048',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        try {
            $file = $request->file('logo');

            if (!$file || !$file->isValid()) {
                return response()->json([
                    'message' => 'Invalid file upload',
                ], 422);
            }

            // Delete old logo if exists
            $oldLogo = Setting::get('app_logo');
            if ($oldLogo && is_string($oldLogo) && trim($oldLogo) !== '' && Storage::disk('public')->exists($oldLogo)) {
                Storage::disk('public')->delete($oldLogo);
            }

            // Store new logo with original extension using file contents (Windows compatible)
            $extension = $file->getClientOriginalExtension();
            $filename = uniqid('logo_') . '.' . $extension;
            $path = 'logos/' . $filename;

            // Read file contents and put to storage (bypasses path issues on Windows)
            Storage::disk('public')->put($path, file_get_contents($file->getRealPath() ?: $file->getPathname()));

            // Save to settings
            Setting::set('app_logo', $path, 'file');
            Setting::clearCache();

            return response()->json([
                'message' => 'Logo uploaded successfully',
                'logo_url' => Storage::url($path),
                'logo_path' => $path,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to upload logo',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Delete logo
     */
    public function deleteLogo(): JsonResponse
    {
        try {
            $oldLogo = Setting::get('app_logo');

            if ($oldLogo && is_string($oldLogo) && trim($oldLogo) !== '' && Storage::disk('public')->exists($oldLogo)) {
                Storage::disk('public')->delete($oldLogo);
            }

            Setting::set('app_logo', null, 'file');
            Setting::clearCache();

            return response()->json([
                'message' => 'Logo deleted successfully',
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to delete logo',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Upload image file (favicon, logos, icons, etc.)
     */
    public function uploadImage(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'image' => 'required|image|mimes:jpeg,png,jpg,gif,svg,ico|max:2048',
            'key' => 'required|string|in:logo_light,logo_dark,favicon,app_icon,og_image',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        try {
            $file = $request->file('image');
            $key = $request->input('key');

            if (!$file || !$file->isValid()) {
                return response()->json([
                    'message' => 'Invalid file upload',
                ], 422);
            }

            // Delete old file if exists
            $oldFile = Setting::get($key);
            if ($oldFile && is_string($oldFile) && trim($oldFile) !== '' && Storage::disk('public')->exists($oldFile)) {
                Storage::disk('public')->delete($oldFile);
            }

            // Store new file
            $extension = $file->getClientOriginalExtension();
            $filename = uniqid($key . '_') . '.' . $extension;
            $path = 'images/' . $filename;

            Storage::disk('public')->put($path, file_get_contents($file->getRealPath() ?: $file->getPathname()));

            // Save to settings
            Setting::set($key, $path, 'file');
            Setting::clearCache();

            return response()->json([
                'message' => 'Image uploaded successfully',
                'url' => Storage::url($path),
                'path' => $path,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to upload image',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Delete image file
     */
    public function deleteImage(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'key' => 'required|string|in:logo_light,logo_dark,favicon,app_icon,og_image',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        try {
            $key = $request->input('key');
            $oldFile = Setting::get($key);

            if ($oldFile && is_string($oldFile) && trim($oldFile) !== '' && Storage::disk('public')->exists($oldFile)) {
                Storage::disk('public')->delete($oldFile);
            }

            Setting::set($key, null, 'file');
            Setting::clearCache();

            return response()->json([
                'message' => 'Image deleted successfully',
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to delete image',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Test email configuration
     */
    public function testEmail(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'recipient' => 'required|email',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        try {
            // TODO: Implement email test
            // Mail::raw('This is a test email from Fellowship.', function ($message) use ($request) {
            //     $message->to($request->input('recipient'))
            //         ->subject('Test Email');
            // });

            return response()->json([
                'message' => 'Test email sent successfully',
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to send test email',
                'error' => $e->getMessage(),
            ], 500);
        }
    }
}
