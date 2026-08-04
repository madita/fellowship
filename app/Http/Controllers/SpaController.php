<?php

namespace App\Http\Controllers;

use App\Models\Page;
use App\Models\Post;
use App\Models\Setting;
use App\Models\Wiki;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class SpaController extends Controller
{
    public function index(Request $request, $any = null)
    {
        // Get base SEO settings
        $seo = $this->getBaseSeoSettings();

        // Try to get content-specific SEO based on the route
        $contentSeo = $this->getContentSeo($any);
        if ($contentSeo) {
            $seo = array_merge($seo, $contentSeo);
        }

        // Build structured data (JSON-LD)
        $structuredData = $this->buildStructuredData($seo, $contentSeo);

        return view('spa', compact('seo', 'structuredData'));
    }

    /**
     * Get base SEO settings from database.
     */
    private function getBaseSeoSettings(): array
    {
        return [
            'app_name' => Setting::get('app_name', config('app.name', 'Fellowship')),
            'meta_title' => Setting::get('meta_title'),
            'meta_description' => Setting::get('meta_description'),
            'meta_keywords' => Setting::get('meta_keywords'),
            'og_title' => Setting::get('og_title'),
            'og_description' => Setting::get('og_description'),
            'og_image' => Setting::get('og_image'),
            'twitter_card_type' => Setting::get('twitter_card_type', 'summary_large_image'),
            'twitter_site' => Setting::get('twitter_site'),
            'canonical_url' => Setting::get('canonical_url'),
            'indexing_enabled' => Setting::get('indexing_enabled', true),
            'app_logo' => Setting::get('app_logo'),
            'content_type' => 'website',
            // Custom scripts
            'custom_head_scripts' => Setting::get('custom_head_scripts', ''),
            'custom_body_scripts' => Setting::get('custom_body_scripts', ''),
        ];
    }

    /**
     * Get SEO data for specific content based on URL.
     */
    private function getContentSeo(?string $path): ?array
    {
        if (empty($path)) {
            return null;
        }

        $segments = explode('/', trim($path, '/'));
        $firstSegment = $segments[0] ?? null;
        $slug = $segments[1] ?? ($segments[0] ?? null);

        // Match different content types
        switch ($firstSegment) {
            case 'wiki':
                return $this->getWikiSeo($slug);

            case 'pages':
                return $this->getPageSeo($slug);

            case 'posts':
            case 'blog':
                return $this->getPostSeo($slug);

            default:
                // Check if it's a direct page slug
                if (count($segments) === 1) {
                    $pageSeo = $this->getPageSeo($firstSegment);
                    if ($pageSeo) {
                        return $pageSeo;
                    }
                }

                return null;
        }
    }

    /**
     * Get SEO data for a wiki page.
     */
    private function getWikiSeo(?string $slug): ?array
    {
        if (empty($slug)) {
            return null;
        }

        $wiki = Wiki::where('slug', $slug)->first();
        if (! $wiki) {
            return null;
        }

        $appName = Setting::get('app_name', 'Fellowship');

        return [
            'meta_title' => $wiki->title.' | '.$appName,
            'og_title' => $wiki->title,
            'content_type' => 'article',
            'canonical_url' => url('/wiki/'.$wiki->slug),
        ];
    }

    /**
     * Get SEO data for a page.
     */
    private function getPageSeo(?string $slug): ?array
    {
        if (empty($slug)) {
            return null;
        }

        $page = Page::where('slug', $slug)->where('published', true)->first();
        if (! $page) {
            return null;
        }

        $appName = Setting::get('app_name', 'Fellowship');
        $description = $this->extractDescription($page->content);

        return [
            'meta_title' => $page->title.' | '.$appName,
            'meta_description' => $description,
            'og_title' => $page->title,
            'og_description' => $description,
            'content_type' => 'article',
            'canonical_url' => url('/pages/'.$page->slug),
            'published_time' => $page->created_at?->toIso8601String(),
            'modified_time' => $page->updated_at?->toIso8601String(),
        ];
    }

    /**
     * Get SEO data for a blog post.
     */
    private function getPostSeo(?string $slug): ?array
    {
        if (empty($slug)) {
            return null;
        }

        $post = Post::where('slug', $slug)->where('status', 'published')->first();
        if (! $post) {
            return null;
        }

        $appName = Setting::get('app_name', 'Fellowship');
        $description = $this->extractDescription($post->content ?? $post->excerpt ?? '');

        return [
            'meta_title' => $post->title.' | '.$appName,
            'meta_description' => $description,
            'og_title' => $post->title,
            'og_description' => $description,
            'content_type' => 'article',
            'canonical_url' => url('/posts/'.$post->slug),
            'published_time' => $post->created_at?->toIso8601String(),
            'modified_time' => $post->updated_at?->toIso8601String(),
        ];
    }

    /**
     * Extract a description from HTML content.
     */
    private function extractDescription(?string $content, int $maxLength = 160): string
    {
        if (empty($content)) {
            return '';
        }

        // Strip HTML tags
        $text = strip_tags($content);
        // Decode HTML entities
        $text = html_entity_decode($text, ENT_QUOTES, 'UTF-8');
        // Normalize whitespace
        $text = preg_replace('/\s+/', ' ', $text);
        $text = trim($text);

        // Truncate to max length
        if (strlen($text) > $maxLength) {
            $text = Str::limit($text, $maxLength - 3, '...');
        }

        return $text;
    }

    /**
     * Build JSON-LD structured data.
     */
    private function buildStructuredData(array $seo, ?array $contentSeo): array
    {
        $appName = $seo['app_name'] ?? 'Fellowship';
        $siteUrl = config('app.url');

        // Base organization/website schema
        $data = [
            '@context' => 'https://schema.org',
            '@graph' => [
                [
                    '@type' => 'WebSite',
                    '@id' => $siteUrl.'/#website',
                    'url' => $siteUrl,
                    'name' => $appName,
                    'description' => $seo['meta_description'] ?? '',
                    'potentialAction' => [
                        '@type' => 'SearchAction',
                        'target' => [
                            '@type' => 'EntryPoint',
                            'urlTemplate' => $siteUrl.'/search?q={search_term_string}',
                        ],
                        'query-input' => 'required name=search_term_string',
                    ],
                ],
            ],
        ];

        // Add article schema for content pages
        if ($contentSeo && ($seo['content_type'] ?? null) === 'article') {
            $article = [
                '@type' => 'Article',
                '@id' => ($seo['canonical_url'] ?? url()->current()).'/#article',
                'headline' => $seo['og_title'] ?? $seo['meta_title'] ?? '',
                'description' => $seo['og_description'] ?? $seo['meta_description'] ?? '',
                'url' => $seo['canonical_url'] ?? url()->current(),
                'isPartOf' => ['@id' => $siteUrl.'/#website'],
            ];

            if (! empty($seo['published_time'])) {
                $article['datePublished'] = $seo['published_time'];
            }
            if (! empty($seo['modified_time'])) {
                $article['dateModified'] = $seo['modified_time'];
            }
            if (! empty($seo['og_image'])) {
                $imageUrl = str_starts_with($seo['og_image'], 'http')
                    ? $seo['og_image']
                    : asset('storage/'.$seo['og_image']);
                $article['image'] = $imageUrl;
            }

            $data['@graph'][] = $article;
        }

        return $data;
    }
}
