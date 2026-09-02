<?php

namespace App\Jobs\Migrations;

use App\Models\Page;
use App\Models\Wiki;
use Illuminate\Support\Str;

class MigrateWikiLinkingJob extends BaseMigrationJob
{
    protected function getMigrationKey(): string
    {
        return 'wikiLinking';
    }

    protected function runMigration(): void
    {
        $wikis = Wiki::all();
        $this->setTotal($wikis->count());
        $this->log('info', 'Processing ' . $wikis->count() . ' wiki pages for internal links');

        $linksUpdated = 0;

        foreach ($wikis as $wiki) {
            $data = Page::where('slug', $wiki->slug)->first();

            if (!$data) {
                $this->progress($wiki->slug);
                continue;
            }

            $content = $data->content;
            $originalContent = $content;

            // Process internal wiki links [[Page Title|Display Text]]
            preg_match_all("/\[\[(.*?)(\|(.*?))?\]\]/", $content, $matches);

            foreach ($matches[0] as $key => $item) {
                // Get anchor part if exists
                $titleParts = explode("#", $matches[1][$key]);
                // title lives in page_translations — match via the translation.
                $page = Page::whereTranslation('title', $titleParts[0])->first();

                if ($page) {
                    $title = isset($matches[3][$key]) && trim($matches[3][$key]) != "" ? $matches[3][$key] : $page->title;
                    $alternative = isset($matches[3][$key]) && trim($matches[3][$key]) != "" ? $matches[3][$key] : null;

                    $replace = "<a wiki-id=\"{$page->id}\" data-title=\"{$page->title}\" data-linked-resource-type=\"wikiable\" alternative=\"{$alternative}\" href=\"/wiki/{$page->slug}\" contenteditable=\"false\">{$title}</a>";
                    $content = Str::replace($item, $replace, $content);
                    $linksUpdated++;
                }
            }

            // Process external links
            preg_match_all("/\[(https?:\/\/[^\s\]]+)\s(.*?)\]/", $content, $matches);

            foreach ($matches[0] as $key => $item) {
                $link = "<a href=\"{$matches[1][$key]}\">{$matches[2][$key]}</a>";
                $content = Str::replace($item, $link, $content);
            }

            // Only update if content changed
            if ($content !== $originalContent) {
                $data->update(['content' => $content]);
            }

            $this->progress($wiki->slug);

            // Memory management
            if ($this->log->processed_items % 50 === 0) {
                gc_collect_cycles();
            }
        }

        $this->log('info', "Wiki linking complete: {$this->log->processed_items} pages, {$linksUpdated} links updated");
    }
}
