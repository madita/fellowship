<?php

namespace App\Http\Controllers;

use App\Models\Page;
use App\Models\Post;
use App\Models\Setting;
use App\Models\Wiki;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Cache;

class SitemapController extends Controller
{
    /**
     * Generate the sitemap index (sitemap_index.xml).
     */
    public function sitemapIndex(): Response
    {
        $sitemapEnabled = Setting::get('sitemap_enabled', true);
        if (!$sitemapEnabled) {
            abort(404);
        }

        $content = Cache::remember('sitemap_index.xml', 3600, function () {
            return $this->generateSitemapIndex();
        });

        return response($content, 200)
            ->header('Content-Type', 'application/xml');
    }

    /**
     * Generate the sitemap index content.
     */
    private function generateSitemapIndex(): string
    {
        $baseUrl = config('app.url');

        $sitemaps = [
            ['loc' => $baseUrl.'/sitemap.xml', 'lastmod' => now()->toW3cString()],
            ['loc' => $baseUrl.'/sitemap-pages.xml', 'lastmod' => $this->getLastModified(Page::class)],
            ['loc' => $baseUrl.'/sitemap-wiki.xml', 'lastmod' => $this->getLastModified(Wiki::class)],
            ['loc' => $baseUrl.'/sitemap-posts.xml', 'lastmod' => $this->getLastModified(Post::class)],
        ];

        $xml = '<?xml version="1.0" encoding="UTF-8"?>'.PHP_EOL;
        $xml .= '<sitemapindex xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">'.PHP_EOL;

        foreach ($sitemaps as $sitemap) {
            $xml .= '  <sitemap>'.PHP_EOL;
            $xml .= '    <loc>'.htmlspecialchars($sitemap['loc']).'</loc>'.PHP_EOL;
            if (!empty($sitemap['lastmod'])) {
                $xml .= '    <lastmod>'.$sitemap['lastmod'].'</lastmod>'.PHP_EOL;
            }
            $xml .= '  </sitemap>'.PHP_EOL;
        }

        $xml .= '</sitemapindex>';

        return $xml;
    }

    /**
     * Get last modified date for a model.
     */
    private function getLastModified(string $modelClass): ?string
    {
        try {
            $latest = $modelClass::orderBy('updated_at', 'desc')->first();

            return $latest?->updated_at?->toW3cString();
        } catch (\Exception $e) {
            return now()->toW3cString();
        }
    }

    /**
     * Generate the main sitemap.xml (static pages + homepage).
     */
    public function index(): Response
    {
        $sitemapEnabled = Setting::get('sitemap_enabled', true);
        if (!$sitemapEnabled) {
            abort(404);
        }

        $content = Cache::remember('sitemap.xml', 3600, function () {
            return $this->generateMainSitemap();
        });

        return response($content, 200)
            ->header('Content-Type', 'application/xml');
    }

    /**
     * Generate sitemap for wiki pages.
     */
    public function wikiSitemap(): Response
    {
        $sitemapEnabled = Setting::get('sitemap_enabled', true);
        if (!$sitemapEnabled) {
            abort(404);
        }

        $content = Cache::remember('sitemap-wiki.xml', 3600, function () {
            return $this->generateWikiSitemap();
        });

        return response($content, 200)
            ->header('Content-Type', 'application/xml');
    }

    /**
     * Generate sitemap for pages.
     */
    public function pagesSitemap(): Response
    {
        $sitemapEnabled = Setting::get('sitemap_enabled', true);
        if (!$sitemapEnabled) {
            abort(404);
        }

        $content = Cache::remember('sitemap-pages.xml', 3600, function () {
            return $this->generatePagesSitemap();
        });

        return response($content, 200)
            ->header('Content-Type', 'application/xml');
    }

    /**
     * Generate sitemap for posts.
     */
    public function postsSitemap(): Response
    {
        $sitemapEnabled = Setting::get('sitemap_enabled', true);
        if (!$sitemapEnabled) {
            abort(404);
        }

        $content = Cache::remember('sitemap-posts.xml', 3600, function () {
            return $this->generatePostsSitemap();
        });

        return response($content, 200)
            ->header('Content-Type', 'application/xml');
    }

    /**
     * Generate main sitemap (homepage + static pages).
     */
    private function generateMainSitemap(): string
    {
        $baseUrl = config('app.url');

        $urls = [
            ['loc' => $baseUrl, 'changefreq' => 'daily', 'priority' => '1.0'],
            ['loc' => $baseUrl.'/about', 'changefreq' => 'monthly', 'priority' => '0.8'],
            ['loc' => $baseUrl.'/contact', 'changefreq' => 'monthly', 'priority' => '0.7'],
            ['loc' => $baseUrl.'/wiki', 'changefreq' => 'weekly', 'priority' => '0.9'],
        ];

        return $this->buildUrlsetXml($urls);
    }

    /**
     * Generate wiki sitemap.
     */
    private function generateWikiSitemap(): string
    {
        $baseUrl = config('app.url');
        $urls = [];

        $wikis = Wiki::all();
        foreach ($wikis as $wiki) {
            $urls[] = [
                'loc'        => $baseUrl.'/wiki/'.$wiki->slug,
                'lastmod'    => $wiki->updated_at?->toW3cString(),
                'changefreq' => 'weekly',
                'priority'   => '0.8',
            ];
        }

        return $this->buildUrlsetXml($urls);
    }

    /**
     * Generate pages sitemap.
     */
    private function generatePagesSitemap(): string
    {
        $baseUrl = config('app.url');
        $urls = [];

        $pages = Page::where('published', true)->get();
        foreach ($pages as $page) {
            $urls[] = [
                'loc'        => $baseUrl.'/pages/'.$page->slug,
                'lastmod'    => $page->updated_at?->toW3cString(),
                'changefreq' => 'weekly',
                'priority'   => '0.7',
            ];
        }

        return $this->buildUrlsetXml($urls);
    }

    /**
     * Generate posts sitemap.
     */
    private function generatePostsSitemap(): string
    {
        $baseUrl = config('app.url');
        $urls = [];

        try {
            $posts = Post::where('published', true)->get();
            foreach ($posts as $post) {
                $urls[] = [
                    'loc'        => $baseUrl.'/posts/'.$post->slug,
                    'lastmod'    => $post->updated_at?->toW3cString(),
                    'changefreq' => 'monthly',
                    'priority'   => '0.6',
                ];
            }
        } catch (\Exception $e) {
            // Posts table might not exist
        }

        return $this->buildUrlsetXml($urls);
    }

    /**
     * Build URL set XML from array of URLs.
     */
    private function buildUrlsetXml(array $urls): string
    {
        $xml = '<?xml version="1.0" encoding="UTF-8"?>'.PHP_EOL;
        $xml .= '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">'.PHP_EOL;

        foreach ($urls as $url) {
            $xml .= '  <url>'.PHP_EOL;
            $xml .= '    <loc>'.htmlspecialchars($url['loc']).'</loc>'.PHP_EOL;
            if (!empty($url['lastmod'])) {
                $xml .= '    <lastmod>'.$url['lastmod'].'</lastmod>'.PHP_EOL;
            }
            if (!empty($url['changefreq'])) {
                $xml .= '    <changefreq>'.$url['changefreq'].'</changefreq>'.PHP_EOL;
            }
            if (!empty($url['priority'])) {
                $xml .= '    <priority>'.$url['priority'].'</priority>'.PHP_EOL;
            }
            $xml .= '  </url>'.PHP_EOL;
        }

        $xml .= '</urlset>';

        return $xml;
    }

    /**
     * Generate robots.txt.
     */
    public function robots(): Response
    {
        $baseUrl = config('app.url');
        $indexingEnabled = Setting::get('indexing_enabled', true);
        $customRobots = Setting::get('robots_txt_custom', '');

        $content = '';

        if (!$indexingEnabled) {
            // Block all crawlers
            $content = "User-agent: *\nDisallow: /\n";
        } else {
            $content = "User-agent: *\n";
            $content .= "Allow: /\n";
            $content .= "\n";
            // Disallow admin and auth routes
            $content .= "Disallow: /admin/\n";
            $content .= "Disallow: /auth/\n";
            $content .= "Disallow: /api/\n";
            $content .= "Disallow: /dashboard/\n";
            $content .= "\n";
            // Add sitemap references
            $content .= "Sitemap: {$baseUrl}/sitemap_index.xml\n";
            $content .= "Sitemap: {$baseUrl}/sitemap.xml\n";
        }

        // Append custom robots.txt content
        if (!empty($customRobots)) {
            $content .= "\n# Custom rules\n";
            $content .= $customRobots."\n";
        }

        return response($content, 200)
            ->header('Content-Type', 'text/plain');
    }
}
