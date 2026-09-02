<?php

namespace App\Jobs\Migrations;

use App\Models\Page;
use App\Models\Tag\Term;
use App\Models\Wiki;
use Exception;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Stevebauman\Purify\Facades\Purify;

class MigrateWikiPagesJob extends BaseMigrationJob
{
    protected string $archivePath = "C:\\Users\\madit\\OneDrive\\Pictures\\Wache\\website\\wachewiki";

    protected function getMigrationKey(): string
    {
        return 'wikiPages';
    }

    protected function runMigration(): void
    {
        $wpages = DB::connection('stadtwache')->select('select distinct(page_title), page_is_redirect from ww_page');
        $this->setTotal(count($wpages));
        $this->log('info', 'Found ' . count($wpages) . ' wiki pages to migrate');

        foreach ($wpages as $wpage) {
            try {
                $status = $wpage->page_is_redirect === 1 ? 'redirect' : null;

                // Fetch page revisions from MediaWiki API
                $url = "https://stadtwache.net/wachewiki/api.php?action=query&prop=revisions&titles={$wpage->page_title}&rvslots=*&rvprop=content|timestamp&formatversion=2&rvlimit=max&format=json&rvdir=newer";

                $response = Http::timeout(60)->get($url);
                $pageresponse = $response->json();

                if (!isset($pageresponse['query']['pages'][0]['revisions'])) {
                    $this->progress($wpage->page_title);
                    continue;
                }

                $page_title = Str::replace("Portal:", "", $pageresponse['query']['pages'][0]['title']);
                $revisions = $pageresponse['query']['pages'][0]['revisions'];

                // Create page with initial content
                $page = Page::create([
                    'title' => $page_title,
                    'user_id' => 1,
                    'content' => $revisions[0]['slots']['main']['content'],
                    'created_at' => $revisions[0]['timestamp'],
                    'updated_at' => $revisions[0]['timestamp']
                ]);

                // Create wiki entry
                $wiki = new Wiki(['title' => $page_title, 'slug' => $page->slug, 'status' => $status]);
                $page->wikiable()->save($wiki);

                // Process all revisions
                foreach ($revisions as $revision) {
                    $page->update([
                        'content' => $revision['slots']['main']['content'],
                        'updated_at' => $revision['timestamp']
                    ]);
                }

                // Transform final content
                $lastContent = $revisions[count($revisions) - 1]['slots']['main']['content'];
                $lastContent = $this->transformWikiContent($lastContent, $page);

                // Extract and assign categories
                $this->assignCategories($lastContent, $page);

                // Clean up templates
                $lastContent = preg_replace('/{{(.*?)}}/', '', $lastContent);

                // Wrap in paragraphs
                $lastContent = $this->wrapInParagraphs($lastContent);

                $page->update(['content' => $lastContent]);

                $this->progress($page_title);

                // Rate limit and memory management
                usleep(100000); // 100ms delay
                unset($page, $wiki, $revisions, $lastContent);

                if ($this->log->processed_items % 10 === 0) {
                    gc_collect_cycles();
                }
            } catch (MigrationCancelledException $e) {
                throw $e;
            } catch (Exception $e) {
                $this->error("Error migrating page {$wpage->page_title}: " . $e->getMessage());
            }
        }

        $this->log('info', "Wiki pages migration complete: {$this->log->processed_items} processed, {$this->log->error_count} errors");
    }

    protected function transformWikiContent(string $content, Page $page): string
    {
        // Headers
        $content = preg_replace('/=====(.*?)=====/', '<h5 id="$1">$1</h5>', $content);
        $content = preg_replace('/====(.*?)====/', '<h4 id="$1">$1</h4>', $content);
        $content = preg_replace('/===(.*?)===/', '<h3 id="$1">$1</h3>', $content);
        $content = preg_replace('/==(.*?)==/', '<h2 id="$1">$1</h2>', $content);

        // Date templates
        $content = preg_replace('/{{dts\|(\d+)\|(\d+)\|(\d+)}}/', '$1.$2.$3', $content);

        // Clean up special characters
        $content = Str::replace("‎", "", $content);

        // Process lists
        $content = $this->processLists($content);

        // Text formatting
        $content = preg_replace('/\'\'\'(.*?)\'\'\'/', '<strong class="highlight">$1</strong>', $content);
        $content = preg_replace('/\'\'(.*?)\'\'/', '<em class="highlight">$1</em>', $content);

        // Category links
        preg_match_all("/\[\[:Kategorie:(.*?)(\|(.*?))?\]\]/", $content, $matches);
        foreach ($matches[0] as $key => $item) {
            $term = Term::where('slug', Str::slug($matches[1][$key]))->first();
            if ($term === null) {
                $tag = Str::replace("_", " ", $matches[1][$key]);
                $term = Term::firstOrCreateByTitle(trim($tag));
            }

            $title = isset($matches[3][$key]) && trim($matches[3][$key]) != "" ? $matches[3][$key] : $term->title;
            $alternative = isset($matches[3][$key]) && trim($matches[3][$key]) != "" ? $matches[3][$key] : null;
            $replace = "<a style=\"font-weight:600\" data-term-id=\"{$term->id}\" data-tag=\"{$term->title}\" data-linked-resource-type=\"terms\" data-alternative=\"{$alternative}\" href=\"/wiki/category/{$term->slug}\" contenteditable=\"false\">#{$title}</a>";
            $content = Str::replace($item, $replace, $content);
        }

        // Process images
        $content = $this->processImages($content, $page);

        // Clean up category/language tags
        $content = preg_replace('/\[\[Kategorie:(.*?)(\|(.*?))?\]\]/', '', $content);
        $content = preg_replace('/\[\[de:(.*?)(\|(.*?))?\]\]/', '', $content);
        $content = preg_replace('/\[\[en:(.*?)(\|(.*?))?\]\]/', '', $content);

        // External links
        preg_match_all("/\[(https?:\/\/[^\s\]]+)\s(.*?)\]/", $content, $matches);
        foreach ($matches[0] as $key => $item) {
            $link = "<a href=\"{$matches[1][$key]}\">{$matches[2][$key]}</a>";
            $content = Str::replace($item, $link, $content);
        }

        // Discworld wiki links
        $content = preg_replace('/<discwiki>(.*?)<\/discwiki>/', '<a data-linked-resource-type="discwiki" href="https://www.thediscworld.de/index.php/$1">$1</a>', $content);

        return $content;
    }

    protected function processLists(string $content): string
    {
        preg_match_all("/(\s*\*(.*)\n)+/", $content, $matches);

        foreach ($matches[0] as $item) {
            $list = "<ul>";
            $sublist = false;

            foreach (preg_split("/((\r?\n)|(\r\n?))/", $item) as $line) {
                if (Str::startsWith(trim($line), "**")) {
                    if (!$sublist) {
                        $sublist = true;
                        $list .= "<ul>";
                    }
                    $line = trim(Str::replace("**", "", $line));
                    $list .= "<li>{$line}</li>";
                } else {
                    if ($sublist) {
                        $sublist = false;
                        $list .= "</ul>";
                    }
                    if (Str::startsWith(trim($line), "*")) {
                        $line = trim(Str::replace("*", "", $line));
                        $list .= "<li>{$line}</li>";
                    }
                }
            }

            if ($sublist) {
                $list .= "</ul>";
            }
            $list .= "</ul>";
            $content = Str::replace($item, $list, $content);
        }

        return $content;
    }

    protected function processImages(string $content, Page $page): string
    {
        // Image:filename pattern
        preg_match_all("/\[\[Image:(.*?)(\|.*)?\]\]/", $content, $matches);
        foreach ($matches[0] as $key => $item) {
            try {
                $pathToFile = $this->archivePath . "\\" . Str::replace(" ", "_", $matches[1][$key]);
                $pathToFileTemp = $this->archivePath . "\\archive\\" . Str::replace(" ", "_", $matches[1][$key]);

                if (file_exists($pathToFileTemp)) {
                    copy($pathToFileTemp, $pathToFile);
                    $image = $page->addMedia($pathToFile)->toMediaCollection('images');
                    $alt = explode("|", $item);
                    $altText = Str::replace("'", "", $alt[count($alt) - 1]);
                    $replace = "<img src=\"{$image->getUrl()}\" alt=\"{$altText}\">";
                    $content = Str::replace($item, $replace, $content);
                }
            } catch (Exception $e) {
                // Skip image on error
            }
        }

        // Bild:filename pattern
        preg_match_all("/\[\[Bild:(.*?)(\|.*)?\]\]/", $content, $matches);
        foreach ($matches[0] as $key => $item) {
            try {
                $pathToFile = $this->archivePath . "\\" . Str::replace(" ", "_", $matches[1][$key]);
                $pathToFileTemp = $this->archivePath . "\\archive\\" . Str::replace(" ", "_", $matches[1][$key]);

                if (file_exists($pathToFileTemp)) {
                    copy($pathToFileTemp, $pathToFile);
                    $image = $page->addMedia($pathToFile)->toMediaCollection('images');
                    $alt = explode("|", $item);
                    $altText = Str::replace("'", "", $alt[count($alt) - 1]);
                    $replace = "<img src=\"{$image->getUrl()}\" alt=\"{$altText}\">";
                    $content = Str::replace($item, $replace, $content);
                }
            } catch (Exception $e) {
                // Skip image on error
            }
        }

        return $content;
    }

    protected function assignCategories(string $content, Page $page): void
    {
        preg_match_all("/\[\[Kategorie:(.*?)(\|(.*?))?\]\]/", $content, $matches);

        $terms = [];
        foreach ($matches[1] as $item) {
            $item = preg_replace('/[^\x20-\x7E]/', '', $item);
            $terms[] = strip_tags($item);
        }

        $terms = array_values(array_unique($terms));

        foreach ($terms as $term) {
            $term = Str::replace("_", " ", $term);
            if (!$page->hasCategory(trim($term), 'wiki')) {
                $page->addCategory(trim($term), 'wiki');
            }
        }
    }

    protected function wrapInParagraphs(string $content): string
    {
        foreach (preg_split("/((\r?\n)|(\r\n?))/", $content) as $line) {
            $paragraph = "<p>{$line}</p>";
            if (trim($line) != "" && $line != " " && $paragraph != "<p></p>" && $paragraph != "<p> </p>" && !preg_match('/<\/h\d>/', $line)) {
                $content = Str::replace($line, $paragraph, $content);
            }
        }

        return $content;
    }
}
