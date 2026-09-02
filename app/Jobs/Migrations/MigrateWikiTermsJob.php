<?php

namespace App\Jobs\Migrations;

use App\Helpers\TaxonomyHelper;
use App\Models\Tag\Taxonomy;
use App\Models\Tag\Term;
use Exception;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

class MigrateWikiTermsJob extends BaseMigrationJob
{
    protected function getMigrationKey(): string
    {
        return 'wikiTerms';
    }

    protected function runMigration(): void
    {
        $categories = DB::connection('stadtwache')->select('select * from ww_category order by cat_subcats desc');
        $this->setTotal(count($categories));
        $this->log('info', 'Found ' . count($categories) . ' wiki categories to migrate');

        foreach ($categories as $category) {
            try {
                // Fetch category info from MediaWiki API
                $url = "https://stadtwache.net/wachewiki/api.php?action=query&cmtitle=Kategorie:{$category->cat_title}&list=categorymembers&prop=revisions&titles=Kategorie:{$category->cat_title}&rvslots=*&rvprop=content&formatversion=2&rvlimit=1&cmlimit=1000&format=json";

                $responserev = Http::timeout(30)->get($url);
                $catresponserev = $responserev->json();

                $desc = '';
                if (isset($catresponserev['query']['pages'][0]['revisions'])) {
                    $desc = $catresponserev['query']['pages'][0]['revisions'][0]['slots']['main']['content'];
                }

                // Fetch subcategories
                $url = "https://stadtwache.net/wachewiki/api.php?action=query&list=categorymembers&cmtitle=Kategorie:{$category->cat_title}&cmtype=subcat&cmlimit=1000&format=json";

                $response = Http::timeout(30)->get($url);
                $catresponse = $response->json();
                $members = $catresponse['query']['categorymembers'] ?? [];

                $categoryname = Str::replace("Kategorie:", "", $category->cat_title);
                $tag = Str::replace("_", " ", $categoryname);
                $term = Term::firstOrCreateByTitle(trim(Str::replace("_", " ", $tag)));

                if ($category->cat_subcats > 0) {
                    $taxonomy = Taxonomy::firstOrNew(['taxonomy' => 'wiki', 'term_id' => $term->id]);
                    $taxonomy->description = $desc;
                    $taxonomy->save();

                    foreach ($members as $member) {
                        if (Str::contains($member['title'], "Kategorie:")) {
                            $cat = Str::replace("Kategorie:", "", $member['title']);
                            $tag = Str::replace("_", " ", $cat);
                            TaxonomyHelper::createTaxables(trim($tag), 'wiki', $taxonomy->id);
                        }
                    }
                } else {
                    $taxonomy = Taxonomy::firstOrNew(['taxonomy' => 'wiki', 'term_id' => $term->id]);
                    $taxonomy->description = $desc;
                    $taxonomy->save();
                }

                $this->progress($category->cat_title);

                // Rate limit API calls
                usleep(100000); // 100ms delay between API calls
            } catch (MigrationCancelledException $e) {
                throw $e;
            } catch (Exception $e) {
                $this->error("Error migrating category {$category->cat_title}: " . $e->getMessage());
            }

            if ($this->log->processed_items % 20 === 0) {
                gc_collect_cycles();
            }
        }

        $this->log('info', "Wiki terms migration complete: {$this->log->processed_items} processed, {$this->log->error_count} errors");
    }
}
