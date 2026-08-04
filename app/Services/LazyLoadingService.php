<?php

namespace App\Services;

use App\Models\Setting;

class LazyLoadingService
{
    /**
     * Check if lazy loading is enabled.
     */
    public static function isEnabled(): bool
    {
        $setting = Setting::get('lazy_loading_enabled', true);

        if (is_string($setting)) {
            return $setting === 'true' || $setting === '1';
        }

        return (bool) $setting;
    }

    /**
     * Get the loading attribute value based on settings.
     *
     * @return string|null 'lazy', 'eager', or null
     */
    public static function getLoadingAttribute(): ?string
    {
        return static::isEnabled() ? 'lazy' : null;
    }

    /**
     * Get lazy loading settings for frontend.
     */
    public static function getSettings(): array
    {
        return [
            'enabled' => static::isEnabled(),
            'loading_attribute' => static::getLoadingAttribute(),
            'intersection_threshold' => 0.1, // 10% visible before loading
            'root_margin' => '50px', // Start loading 50px before entering viewport
        ];
    }

    /**
     * Add lazy loading attributes to an img tag.
     *
     * @param  string  $html  HTML containing img tags
     * @return string Modified HTML with lazy loading attributes
     */
    public static function processHtml(string $html): string
    {
        if (! static::isEnabled()) {
            return $html;
        }

        // Add loading="lazy" to img tags that don't already have it
        return preg_replace_callback(
            '/<img\s+([^>]*?)>/i',
            function ($matches) {
                $attributes = $matches[1];

                // Skip if already has loading attribute
                if (preg_match('/\bloading\s*=/i', $attributes)) {
                    return $matches[0];
                }

                // Skip if it's a data URI or inline SVG
                if (preg_match('/src\s*=\s*["\']data:/i', $attributes)) {
                    return $matches[0];
                }

                // Add loading="lazy"
                return '<img loading="lazy" '.$attributes.'>';
            },
            $html
        );
    }

    /**
     * Add lazy loading to iframe tags as well.
     *
     * @param  string  $html  HTML containing iframe tags
     * @return string Modified HTML with lazy loading attributes
     */
    public static function processIframes(string $html): string
    {
        if (! static::isEnabled()) {
            return $html;
        }

        return preg_replace_callback(
            '/<iframe\s+([^>]*?)>/i',
            function ($matches) {
                $attributes = $matches[1];

                // Skip if already has loading attribute
                if (preg_match('/\bloading\s*=/i', $attributes)) {
                    return $matches[0];
                }

                return '<iframe loading="lazy" '.$attributes.'>';
            },
            $html
        );
    }

    /**
     * Process all lazy-loadable elements in HTML.
     */
    public static function processAllElements(string $html): string
    {
        $html = static::processHtml($html);
        $html = static::processIframes($html);

        return $html;
    }
}
