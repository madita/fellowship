<?php

namespace App\Http\Controllers\Podcast;

use App\Http\Controllers\Controller;
use App\Models\Podcast\Podcast;
use App\Models\Podcast\PodcastEpisode;
use App\Models\Setting;
use Illuminate\Http\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * The RSS feed a podcast app subscribes to.
 *
 * This is the one part of the feature that is not the SPA: a podcast
 * client fetches XML over plain HTTP, so the route lives in web.php and
 * answers with a document rather than JSON. The iTunes namespace is what
 * every directory reads, which is why the show carries author, category
 * and explicit at all.
 */
class PodcastFeedController extends Controller
{
    private const ITUNES_NS = 'http://www.itunes.com/dtds/podcast-1.0.dtd';

    public function show(string $slug): Response
    {
        // The feature gate is middleware on the API routes; this route is
        // outside that group, so it asks for itself.
        if ( ! filter_var(Setting::get('feature_podcast_enabled', true), FILTER_VALIDATE_BOOLEAN)) {
            throw new NotFoundHttpException();
        }

        // Approved as well as published: a podcast app caches a feed and
        // hands it on, so anything that reaches one is out for good.
        // There is no "own drafts" case here — a feed has no caller.
        $podcast = Podcast::visible()->where('slug', $slug)->first();

        if ( ! $podcast) {
            throw new NotFoundHttpException();
        }

        $episodes = $podcast->publishedEpisodes()->limit(300)->get();

        return response($this->buildFeed($podcast, $episodes), 200)
            ->header('Content-Type', 'application/rss+xml; charset=UTF-8');
    }

    private function buildFeed(Podcast $podcast, $episodes): string
    {
        $siteName = Setting::get('app_name', config('app.name'));
        // Everyone who makes the show, named; the site itself if the
        // show somehow has nobody attached
        $author   = $podcast->authorLine() ?: $siteName;
        $cover    = $this->absolute($podcast->coverUrl());
        $pageUrl  = url("/podcast/{$podcast->slug}");

        $xml = '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
        $xml .= '<rss version="2.0" xmlns:itunes="' . self::ITUNES_NS . '" '
            . 'xmlns:content="http://purl.org/rss/1.0/modules/content/" '
            . 'xmlns:atom="http://www.w3.org/2005/Atom">' . "\n";
        $xml .= "  <channel>\n";
        $xml .= '    <title>' . $this->escape($podcast->title) . "</title>\n";
        $xml .= '    <link>' . $this->escape($pageUrl) . "</link>\n";
        $xml .= '    <language>' . $this->escape($podcast->language ?: 'de') . "</language>\n";
        $xml .= '    <description>' . $this->escape($this->plain($podcast->description)) . "</description>\n";
        $xml .= '    <itunes:author>' . $this->escape($author) . "</itunes:author>\n";
        $xml .= '    <itunes:explicit>' . ($podcast->explicit ? 'true' : 'false') . "</itunes:explicit>\n";
        $xml .= '    <atom:link href="' . $this->escape($podcast->feedUrl())
            . '" rel="self" type="application/rss+xml"/>' . "\n";

        if ($podcast->subtitle) {
            $xml .= '    <itunes:subtitle>' . $this->escape($podcast->subtitle) . "</itunes:subtitle>\n";
        }

        // A directory accepts several categories, so every term the show
        // was given gets its own element
        foreach ($podcast->categoryTerms() as $term) {
            $xml .= '    <itunes:category text="' . $this->escape($term['title']) . '"/>' . "\n";
        }

        if ($cover) {
            $xml .= '    <itunes:image href="' . $this->escape($cover) . '"/>' . "\n";
            $xml .= "    <image>\n";
            $xml .= '      <url>' . $this->escape($cover) . "</url>\n";
            $xml .= '      <title>' . $this->escape($podcast->title) . "</title>\n";
            $xml .= '      <link>' . $this->escape($pageUrl) . "</link>\n";
            $xml .= "    </image>\n";
        }

        foreach ($episodes as $episode) {
            $xml .= $this->buildItem($podcast, $episode);
        }

        $xml .= "  </channel>\n";
        $xml .= "</rss>\n";

        return $xml;
    }

    private function buildItem(Podcast $podcast, PodcastEpisode $episode): string
    {
        $audio = $episode->audioUrl();
        $date  = ($episode->published_at ?: $episode->created_at)?->toRfc2822String();
        $url   = url("/podcast/{$podcast->slug}/{$episode->slug}");

        $xml = "    <item>\n";
        $xml .= '      <title>' . $this->escape($episode->title) . "</title>\n";
        $xml .= '      <link>' . $this->escape($url) . "</link>\n";
        // The page is the stable identity; the audio may move hosts
        $xml .= '      <guid isPermaLink="false">' . $this->escape($url) . "</guid>\n";

        if ($date) {
            $xml .= '      <pubDate>' . $this->escape($date) . "</pubDate>\n";
        }

        $xml .= '      <description>' . $this->escape($this->plain($episode->summary)) . "</description>\n";

        if ($episode->show_notes) {
            $xml .= '      <content:encoded><![CDATA[' . $this->cdataSafe($episode->show_notes)
                . "]]></content:encoded>\n";
        }

        if ($audio) {
            $xml .= '      <enclosure url="' . $this->escape($this->absolute($audio))
                . '" length="' . $episode->enclosureLength()
                . '" type="' . $this->escape($episode->audio_mime ?: 'audio/mpeg') . '"/>' . "\n";
        }

        if ($episode->duration) {
            $xml .= '      <itunes:duration>' . $this->formatDuration($episode->duration)
                . "</itunes:duration>\n";
        }

        if ($episode->episode_number) {
            $xml .= '      <itunes:episode>' . (int) $episode->episode_number . "</itunes:episode>\n";
        }

        if ($episode->season_number) {
            $xml .= '      <itunes:season>' . (int) $episode->season_number . "</itunes:season>\n";
        }

        $xml .= '      <itunes:explicit>' . ($episode->explicit ? 'true' : 'false') . "</itunes:explicit>\n";
        $xml .= "    </item>\n";

        return $xml;
    }

    /**
     * HH:MM:SS, which is what directories expect even for short episodes.
     */
    private function formatDuration(int $seconds): string
    {
        return sprintf(
            '%02d:%02d:%02d',
            intdiv($seconds, 3600),
            intdiv($seconds % 3600, 60),
            $seconds % 60
        );
    }

    /**
     * A feed may only carry absolute URLs. Our own files are paths under
     * /storage; an episode hosted elsewhere already is absolute, and
     * handing that to url() would prefix our domain onto someone else's.
     */
    private function absolute(?string $value): ?string
    {
        if ( ! $value) {
            return null;
        }

        return preg_match('#^https?://#i', $value) ? $value : url($value);
    }

    private function plain(?string $value): string
    {
        return trim(html_entity_decode(strip_tags((string) $value), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
    }

    private function escape(?string $value): string
    {
        return htmlspecialchars((string) $value, ENT_QUOTES | ENT_XML1, 'UTF-8');
    }

    /**
     * A "]]>" inside show notes would close the CDATA section early and
     * break the whole feed, so it is split across two sections.
     */
    private function cdataSafe(string $value): string
    {
        return str_replace(']]>', ']]]]><![CDATA[>', $value);
    }
}
