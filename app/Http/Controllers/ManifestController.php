<?php

namespace App\Http\Controllers;

use App\Models\Setting;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Storage;

class ManifestController extends Controller
{
    /**
     * Generate PWA manifest dynamically from settings.
     */
    public function generate(): JsonResponse
    {
        $appName = Setting::get('app_name', config('app.name', 'Fellowship'));
        $appDescription = Setting::get('meta_description', 'Your awesome community platform');
        $primaryColor = Setting::get('primary_color', '#1976D2');
        $backgroundColor = Setting::get('secondary_color', '#FFFFFF');
        $themeColor = Setting::get('primary_color', '#1976D2');

        // Get app icon path
        $appIconPath = Setting::get('app_icon');
        $faviconPath = Setting::get('favicon');

        // Build icons array
        $icons = [];

        // Add app icon if available (must be PNG, WebP, or SVG for PWA)
        if ($appIconPath && Storage::disk('public')->exists($appIconPath)) {
            $iconUrl = Storage::url($appIconPath);

            // Try to get image dimensions
            $fullPath = Storage::disk('public')->path($appIconPath);
            if (file_exists($fullPath)) {
                $imageInfo = @getimagesize($fullPath);
                if ($imageInfo) {
                    $mimeType = $imageInfo['mime'];

                    // Only add if it's PNG, WebP, or SVG (required for PWA)
                    if (in_array($mimeType, ['image/png', 'image/webp', 'image/svg+xml'])) {
                        $width = $imageInfo[0];
                        $height = $imageInfo[1];

                        // Check if square or nearly square
                        $isSquare = abs($width - $height) / max($width, $height) < 0.1;

                        if ($isSquare && $width >= 144) {
                            // Square icon suitable for PWA
                            $icons[] = [
                                'src'     => $iconUrl,
                                'sizes'   => $width.'x'.$height,
                                'type'    => $mimeType,
                                'purpose' => 'any',
                            ];
                        } elseif ($width >= 144 && $height >= 144) {
                            // Non-square but large enough
                            $icons[] = [
                                'src'     => $iconUrl,
                                'sizes'   => $width.'x'.$height,
                                'type'    => $mimeType,
                                'purpose' => 'any',
                            ];
                        }
                    }
                }
            }
        }

        // Add favicon as fallback icon (if PNG/WebP/SVG)
        if ($faviconPath && Storage::disk('public')->exists($faviconPath)) {
            $faviconUrl = Storage::url($faviconPath);
            $fullPath = Storage::disk('public')->path($faviconPath);

            if (file_exists($fullPath)) {
                $imageInfo = @getimagesize($fullPath);
                if ($imageInfo && in_array($imageInfo['mime'], ['image/png', 'image/webp', 'image/svg+xml'])) {
                    $icons[] = [
                        'src'     => $faviconUrl,
                        'sizes'   => $imageInfo[0].'x'.$imageInfo[1],
                        'type'    => $imageInfo['mime'],
                        'purpose' => 'any',
                    ];
                }
            }
        }

        // Default fallback icons (only if files exist)
        if (empty($icons)) {
            if (file_exists(public_path('images/app-icon-192.png'))) {
                $icons[] = [
                    'src'     => '/images/app-icon-192.png',
                    'sizes'   => '192x192',
                    'type'    => 'image/png',
                    'purpose' => 'any maskable',
                ];
            }

            if (file_exists(public_path('images/app-icon-512.png'))) {
                $icons[] = [
                    'src'     => '/images/app-icon-512.png',
                    'sizes'   => '512x512',
                    'type'    => 'image/png',
                    'purpose' => 'any maskable',
                ];
            }

            // If still no icons, create a minimal icon reference to favicon
            if (empty($icons) && file_exists(public_path('favicon.ico'))) {
                $icons[] = [
                    'src'   => '/favicon.ico',
                    'sizes' => '48x48',
                    'type'  => 'image/x-icon',
                ];
            }
        }

        $manifest = [
            'name'             => $appName,
            'short_name'       => $appName,
            'description'      => $appDescription,
            'start_url'        => '/',
            'display'          => 'standalone',
            'background_color' => $backgroundColor,
            'theme_color'      => $themeColor,
            'orientation'      => 'portrait-primary',
            'icons'            => $icons,
            'categories'       => ['social', 'community'],
            'screenshots'      => [],
        ];

        return response()->json($manifest)
            ->header('Content-Type', 'application/manifest+json');
    }
}
