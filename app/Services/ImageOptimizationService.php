<?php

namespace App\Services;

use App\Models\Setting;
use Illuminate\Support\Facades\Config;
use Spatie\Image\Image;
use Spatie\Image\Enums\Fit;

class ImageOptimizationService
{
    /**
     * Check if image optimization is enabled.
     */
    public static function isEnabled(): bool
    {
        $setting = Setting::get('image_optimization_enabled', true);

        if (is_string($setting)) {
            return $setting === 'true' || $setting === '1';
        }

        return (bool) $setting;
    }

    /**
     * Configure the media library optimizers based on settings.
     * Call this in a service provider or middleware.
     */
    public static function configureOptimizers(): void
    {
        if (!static::isEnabled()) {
            // Disable all optimizers by setting an empty array
            Config::set('media-library.image_optimizers', []);
        }
    }

    /**
     * Optimize an image file manually.
     *
     * @param string $path Path to the image file
     * @param array $options Optimization options
     * @return bool
     */
    public static function optimize(string $path, array $options = []): bool
    {
        if (!static::isEnabled()) {
            return false;
        }

        if (!file_exists($path)) {
            return false;
        }

        try {
            $image = Image::load($path);

            // Apply quality settings
            $quality = $options['quality'] ?? 85;
            $image->quality($quality);

            // Apply max dimensions if specified
            if (isset($options['max_width']) || isset($options['max_height'])) {
                $maxWidth = $options['max_width'] ?? null;
                $maxHeight = $options['max_height'] ?? null;

                if ($maxWidth && $maxHeight) {
                    $image->fit(Fit::Max, $maxWidth, $maxHeight);
                } elseif ($maxWidth) {
                    $image->width($maxWidth);
                } elseif ($maxHeight) {
                    $image->height($maxHeight);
                }
            }

            // Save the optimized image
            $image->save($path);

            return true;
        } catch (\Exception $e) {
            \Log::warning('Image optimization failed: ' . $e->getMessage(), [
                'path' => $path,
                'options' => $options,
            ]);
            return false;
        }
    }

    /**
     * Get optimization settings for frontend.
     */
    public static function getSettings(): array
    {
        return [
            'enabled' => static::isEnabled(),
            'max_upload_size' => config('media-library.max_file_size', 10 * 1024 * 1024),
            'allowed_types' => ['image/jpeg', 'image/png', 'image/gif', 'image/webp', 'image/svg+xml'],
        ];
    }
}
