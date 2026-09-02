<?php

namespace App\Jobs\Migrations;

use App\Models\Page;
use App\Models\Tag\Taxonomy;
use App\Models\Tag\Term;
use Illuminate\Support\Str;

class MigrateWikiTermsLinkingJob extends BaseMigrationJob
{
    protected function getMigrationKey(): string
    {
        return 'wikiTermsLinking';
    }

    protected function runMigration(): void
    {
        $taxonomies = Taxonomy::where('taxonomy', 'wiki')
            ->where('description', 'LIKE', '%[[%')
            ->get();

        $this->setTotal($taxonomies->count());
        $this->log('info', 'Processing ' . $taxonomies->count() . ' wiki taxonomies for term links');

        foreach ($taxonomies as $taxonomy) {
            $description = $taxonomy->description;

            // Process category links
            preg_match_all("/\[\[Kategorie:(.*?)(\|(.*?))?\]\]/", $description, $matches);

            foreach ($matches[0] as $key => $item) {
                $term = Term::where('slug', Str::slug($matches[1][$key]))->first();

                if ($term === null) {
                    $tag = Str::replace("_", " ", $matches[1][$key]);
                    $term = Term::firstOrCreateByTitle(trim($tag));
                }

                $title = isset($matches[3][$key]) && trim($matches[3][$key]) != "" ? $matches[3][$key] : $term->title;
                $alternative = isset($matches[3][$key]) && trim($matches[3][$key]) != "" ? $matches[3][$key] : null;
                $replace = "<a style=\"font-weight:600\" term-id=\"{$term->id}\" data-tag=\"{$term->title}\" data-linked-resource-type=\"terms\" alternative=\"{$alternative}\" href=\"/wiki/category/{$term->slug}\" contenteditable=\"false\">#{$title}</a>";
                $description = Str::replace($item, $replace, $description);
            }

            // Process internal wiki links
            preg_match_all("/\[\[(.*?)(\|(.*?))?\]\]/", $description, $matches);

            foreach ($matches[0] as $key => $item) {
                $titleParts = explode("#", $matches[1][$key]);
                // title lives in page_translations — match via the translation.
                $page = Page::whereTranslation('title', $titleParts[0])->first();

                if ($page) {
                    $title = isset($matches[3][$key]) && trim($matches[3][$key]) != "" ? $matches[3][$key] : $page->title;
                    $alternative = isset($matches[3][$key]) && trim($matches[3][$key]) != "" ? $matches[3][$key] : null;

                    $replace = "<a wiki-id=\"{$page->id}\" data-title=\"{$page->title}\" data-linked-resource-type=\"wikiable\" alternative=\"{$alternative}\" href=\"/wiki/{$page->slug}\" contenteditable=\"false\">{$title}</a>";
                    $description = Str::replace($item, $replace, $description);
                }
            }

            $taxonomy->description = $description;
            $taxonomy->save();

            $this->progress($taxonomy->term->title ?? "Taxonomy #{$taxonomy->id}");

            // Memory management
            if ($this->log->processed_items % 20 === 0) {
                gc_collect_cycles();
            }
        }

        $this->log('info', "Wiki terms linking complete: {$this->log->processed_items} processed");
    }
}
