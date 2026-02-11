<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $now = now();

        // Migrate Pages
        $pages = DB::table('pages')->select('id', 'title', 'content', 'created_at', 'updated_at')->get();
        foreach ($pages as $page) {
            DB::table('page_translations')->insert([
                'page_id'    => $page->id,
                'locale'     => 'en',
                'title'      => $page->title ?? '',
                'content'    => $page->content,
                'created_at' => $page->created_at ?? $now,
                'updated_at' => $page->updated_at ?? $now,
            ]);
        }

        // Migrate Posts (posts table has 'content' column, translations use 'body')
        $posts = DB::table('posts')->select('id', 'title', 'content', 'created_at', 'updated_at')->get();
        foreach ($posts as $post) {
            DB::table('post_translations')->insert([
                'post_id'    => $post->id,
                'locale'     => 'en',
                'title'      => $post->title ?? '',
                'body'       => $post->content,
                'created_at' => $post->created_at ?? $now,
                'updated_at' => $post->updated_at ?? $now,
            ]);
        }

        // Migrate Wikis (wikiables table)
        $wikis = DB::table('wikiables')->select('id', 'title', 'created_at', 'updated_at')->get();
        foreach ($wikis as $wiki) {
            DB::table('wiki_translations')->insert([
                'wiki_id'    => $wiki->id,
                'locale'     => 'en',
                'title'      => $wiki->title ?? '',
                'created_at' => $wiki->created_at ?? $now,
                'updated_at' => $wiki->updated_at ?? $now,
            ]);
        }

        // Migrate Events
        $events = DB::table('events')->select('id', 'title', 'description', 'created_at', 'updated_at')->get();
        foreach ($events as $event) {
            DB::table('event_translations')->insert([
                'event_id'    => $event->id,
                'locale'      => 'en',
                'title'       => $event->title ?? '',
                'description' => $event->description,
                'created_at'  => $event->created_at ?? $now,
                'updated_at'  => $event->updated_at ?? $now,
            ]);
        }

        // Migrate Collections
        $collections = DB::table('collections')->select('id', 'name', 'created_at', 'updated_at')->get();
        foreach ($collections as $collection) {
            DB::table('collection_translations')->insert([
                'collection_id' => $collection->id,
                'locale'        => 'en',
                'name'          => $collection->name ?? '',
                'created_at'    => $collection->created_at ?? $now,
                'updated_at'    => $collection->updated_at ?? $now,
            ]);
        }

        // Migrate Terms
        $terms = DB::table('terms')->select('id', 'title', 'content', 'lead', 'created_at', 'updated_at')->get();
        foreach ($terms as $term) {
            DB::table('term_translations')->insert([
                'term_id'    => $term->id,
                'locale'     => 'en',
                'title'      => $term->title ?? '',
                'content'    => $term->content,
                'lead'       => $term->lead,
                'created_at' => $term->created_at ?? $now,
                'updated_at' => $term->updated_at ?? $now,
            ]);
        }

        // Migrate Taxonomies
        $taxonomies = DB::table('taxonomies')->select('id', 'description', 'content', 'lead', 'meta_desc', 'created_at', 'updated_at')->get();
        foreach ($taxonomies as $taxonomy) {
            DB::table('taxonomy_translations')->insert([
                'taxonomy_id' => $taxonomy->id,
                'locale'      => 'en',
                'description' => $taxonomy->description,
                'content'     => $taxonomy->content,
                'lead'        => $taxonomy->lead,
                'meta_desc'   => $taxonomy->meta_desc,
                'created_at'  => $taxonomy->created_at ?? $now,
                'updated_at'  => $taxonomy->updated_at ?? $now,
            ]);
        }

        // Migrate Sections
        $sections = DB::table('sections')->select('id', 'title', 'created_at', 'updated_at')->get();
        foreach ($sections as $section) {
            DB::table('section_translations')->insert([
                'section_id' => $section->id,
                'locale'     => 'en',
                'title'      => $section->title,
                'created_at' => $section->created_at ?? $now,
                'updated_at' => $section->updated_at ?? $now,
            ]);
        }

        // Migrate Widgets
        $widgets = DB::table('widgets')->select('id', 'title', 'content', 'created_at', 'updated_at')->get();
        foreach ($widgets as $widget) {
            DB::table('widget_translations')->insert([
                'widget_id'  => $widget->id,
                'locale'     => 'en',
                'title'      => $widget->title,
                'content'    => $widget->content,
                'created_at' => $widget->created_at ?? $now,
                'updated_at' => $widget->updated_at ?? $now,
            ]);
        }

        // Migrate Homepage Menu Items
        $menuItems = DB::table('homepage_menu_items')->select('id', 'label', 'created_at', 'updated_at')->get();
        foreach ($menuItems as $menuItem) {
            DB::table('homepage_menu_item_translations')->insert([
                'homepage_menu_item_id' => $menuItem->id,
                'locale'                => 'en',
                'label'                 => $menuItem->label ?? '',
                'created_at'            => $menuItem->created_at ?? $now,
                'updated_at'            => $menuItem->updated_at ?? $now,
            ]);
        }
    }

    public function down(): void
    {
        // Delete all English translations (the migrated data)
        DB::table('page_translations')->where('locale', 'en')->delete();
        DB::table('post_translations')->where('locale', 'en')->delete();
        DB::table('wiki_translations')->where('locale', 'en')->delete();
        DB::table('event_translations')->where('locale', 'en')->delete();
        DB::table('collection_translations')->where('locale', 'en')->delete();
        DB::table('term_translations')->where('locale', 'en')->delete();
        DB::table('taxonomy_translations')->where('locale', 'en')->delete();
        DB::table('section_translations')->where('locale', 'en')->delete();
        DB::table('widget_translations')->where('locale', 'en')->delete();
        DB::table('homepage_menu_item_translations')->where('locale', 'en')->delete();
    }
};
