<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Collection;
use App\Models\Event\Event;
use App\Models\HomepageMenuItem;
use App\Models\Page;
use App\Models\Post;
use App\Models\Section;
use App\Models\Tag\Taxonomy;
use App\Models\Tag\Term;
use App\Models\Widget;
use App\Models\Wiki;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ModelTranslationController extends Controller
{
    /**
     * Available translatable models configuration.
     */
    protected array $translatableModels = [
        'page' => [
            'model'      => Page::class,
            'label'      => 'Pages',
            'fields'     => ['title', 'content'],
            'identifier' => 'title',
        ],
        'post' => [
            'model'      => Post::class,
            'label'      => 'Posts',
            'fields'     => ['title', 'body'],
            'identifier' => 'title',
        ],
        'wiki' => [
            'model'      => Wiki::class,
            'label'      => 'Wiki',
            'fields'     => ['title'],
            'identifier' => 'title',
        ],
        'event' => [
            'model'      => Event::class,
            'label'      => 'Events',
            'fields'     => ['title', 'description'],
            'identifier' => 'title',
        ],
        'collection' => [
            'model'      => Collection::class,
            'label'      => 'Collections',
            'fields'     => ['name'],
            'identifier' => 'name',
        ],
        'term' => [
            'model'      => Term::class,
            'label'      => 'Terms',
            'fields'     => ['title', 'content', 'lead'],
            'identifier' => 'title',
        ],
        'taxonomy' => [
            'model'      => Taxonomy::class,
            'label'      => 'Taxonomies',
            'fields'     => ['description', 'content', 'lead', 'meta_desc'],
            'identifier' => 'description',
        ],
        'section' => [
            'model'      => Section::class,
            'label'      => 'Sections',
            'fields'     => ['title'],
            'identifier' => 'title',
        ],
        'widget' => [
            'model'      => Widget::class,
            'label'      => 'Widgets',
            'fields'     => ['title', 'content'],
            'identifier' => 'title',
        ],
        'homepage_menu_item' => [
            'model'      => HomepageMenuItem::class,
            'label'      => 'Menu Items',
            'fields'     => ['label'],
            'identifier' => 'label',
        ],
    ];

    /**
     * Get available locales from config.
     */
    protected function getLocales(): array
    {
        return config('translatable.locales', ['en', 'de']);
    }

    /**
     * List all translatable models with their counts.
     */
    public function index(): JsonResponse
    {
        $models = [];

        foreach ($this->translatableModels as $key => $config) {
            $modelClass = $config['model'];
            $count = $modelClass::withoutGlobalScopes()->count();

            $models[] = [
                'key'        => $key,
                'label'      => $config['label'],
                'fields'     => $config['fields'],
                'count'      => $count,
                'identifier' => $config['identifier'],
            ];
        }

        return response()->json([
            'models'  => $models,
            'locales' => $this->getLocales(),
        ]);
    }

    /**
     * Get translations for a specific model item.
     */
    public function show(string $modelType, int $id): JsonResponse
    {
        if (!isset($this->translatableModels[$modelType])) {
            return response()->json(['error' => 'Invalid model type'], 404);
        }

        $config = $this->translatableModels[$modelType];
        $modelClass = $config['model'];

        $item = $modelClass::withoutGlobalScopes()->with('translations')->find($id);

        if (!$item) {
            return response()->json(['error' => 'Item not found'], 404);
        }

        $translations = [];
        foreach ($this->getLocales() as $locale) {
            $translations[$locale] = [];
            foreach ($config['fields'] as $field) {
                $translations[$locale][$field] = $item->getTranslation($locale)?->{$field} ?? '';
            }
        }

        return response()->json([
            'id'           => $item->id,
            'identifier'   => $item->{$config['identifier']} ?? $item->getTranslation('en')?->{$config['identifier']} ?? "#{$item->id}",
            'fields'       => $config['fields'],
            'translations' => $translations,
            'locales'      => $this->getLocales(),
        ]);
    }

    /**
     * Update translations for a specific model item.
     */
    public function update(Request $request, string $modelType, int $id): JsonResponse
    {
        if (!isset($this->translatableModels[$modelType])) {
            return response()->json(['error' => 'Invalid model type'], 404);
        }

        $config = $this->translatableModels[$modelType];
        $modelClass = $config['model'];

        $item = $modelClass::withoutGlobalScopes()->find($id);

        if (!$item) {
            return response()->json(['error' => 'Item not found'], 404);
        }

        $translations = $request->input('translations', []);

        DB::beginTransaction();
        try {
            foreach ($translations as $locale => $fields) {
                if (!in_array($locale, $this->getLocales())) {
                    continue;
                }

                $data = [];
                foreach ($config['fields'] as $field) {
                    if (array_key_exists($field, $fields)) {
                        $data[$field] = $fields[$field];
                    }
                }

                if (!empty($data)) {
                    $item->translateOrNew($locale)->fill($data);
                }
            }

            $item->save();

            // Sync wiki translations if the model is wikiable
            $this->syncWikiTranslations($item, $translations);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Translations updated successfully',
            ]);
        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json([
                'error'   => 'Failed to update translations',
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Sync wiki translations when a wikiable model's translations are updated.
     */
    protected function syncWikiTranslations($item, array $translations): void
    {
        // Check if the model uses the Wikiable trait (has wikiable() method)
        if (!method_exists($item, 'wikiable')) {
            return;
        }

        // Get the wikiable field mapping using reflection (it's a protected property)
        $wikiableMapping = $this->getWikiableMapping($item);

        if (empty($wikiableMapping)) {
            return;
        }

        // Get associated wiki entries
        $wikis = $item->wikiable()->get();

        if ($wikis->isEmpty()) {
            return;
        }

        foreach ($wikis as $wiki) {
            foreach ($translations as $locale => $fields) {
                $wikiData = [];

                // Map fields from parent model to wiki based on wikiable config
                foreach ($wikiableMapping as $parentField => $wikiField) {
                    // Only sync translatable wiki fields (currently just 'title')
                    if ($wikiField === 'title' && array_key_exists($parentField, $fields)) {
                        $wikiData[$wikiField] = $fields[$parentField];
                    }
                }

                if (!empty($wikiData)) {
                    $wiki->translateOrNew($locale)->fill($wikiData);
                }
            }

            $wiki->save();
        }
    }

    /**
     * Get the wikiable mapping from a model using reflection.
     */
    protected function getWikiableMapping($item): array
    {
        try {
            $reflection = new \ReflectionClass($item);

            if (!$reflection->hasProperty('wikiable')) {
                return [];
            }

            $property = $reflection->getProperty('wikiable');
            $property->setAccessible(true);

            return $property->getValue($item) ?? [];
        } catch (\ReflectionException $e) {
            return [];
        }
    }

    /**
     * Get translation coverage statistics.
     */
    public function stats(): JsonResponse
    {
        $stats = [];
        $locales = $this->getLocales();

        foreach ($this->translatableModels as $key => $config) {
            $modelClass = $config['model'];
            $totalCount = $modelClass::withoutGlobalScopes()->count();

            $modelStats = [
                'key'     => $key,
                'label'   => $config['label'],
                'total'   => $totalCount,
                'locales' => [],
            ];

            foreach ($locales as $locale) {
                // Count items that have a translation for this locale
                $translatedCount = $modelClass::withoutGlobalScopes()
                    ->whereHas('translations', function ($query) use ($locale) {
                        $query->where('locale', $locale);
                    })
                    ->count();

                $modelStats['locales'][$locale] = [
                    'translated' => $translatedCount,
                    'missing'    => $totalCount - $translatedCount,
                    'percentage' => $totalCount > 0 ? round(($translatedCount / $totalCount) * 100, 1) : 100,
                ];
            }

            $stats[] = $modelStats;
        }

        // Calculate overall stats
        $overall = [];
        foreach ($locales as $locale) {
            $totalItems = 0;
            $translatedItems = 0;

            foreach ($stats as $modelStats) {
                $totalItems += $modelStats['total'];
                $translatedItems += $modelStats['locales'][$locale]['translated'];
            }

            $overall[$locale] = [
                'total'      => $totalItems,
                'translated' => $translatedItems,
                'missing'    => $totalItems - $translatedItems,
                'percentage' => $totalItems > 0 ? round(($translatedItems / $totalItems) * 100, 1) : 100,
            ];
        }

        return response()->json([
            'models'  => $stats,
            'overall' => $overall,
            'locales' => $locales,
        ]);
    }

    /**
     * Get items missing translations for a specific locale.
     */
    public function missing(string $locale): JsonResponse
    {
        if (!in_array($locale, $this->getLocales())) {
            return response()->json(['error' => 'Invalid locale'], 400);
        }

        $missing = [];

        foreach ($this->translatableModels as $key => $config) {
            $modelClass = $config['model'];

            // Get items without translation for this locale
            $items = $modelClass::withoutGlobalScopes()
                ->whereDoesntHave('translations', function ($query) use ($locale) {
                    $query->where('locale', $locale);
                })
                ->limit(50)
                ->get();

            if ($items->isNotEmpty()) {
                $missing[$key] = [
                    'label' => $config['label'],
                    'items' => $items->map(function ($item) use ($config) {
                        return [
                            'id'         => $item->id,
                            'identifier' => $item->{$config['identifier']}
                                ?? $item->getTranslation('en')?->{$config['identifier']}
                                ?? "#{$item->id}",
                        ];
                    }),
                ];
            }
        }

        return response()->json([
            'locale'  => $locale,
            'missing' => $missing,
        ]);
    }

    /**
     * Get all items for a model type with their translation status.
     */
    public function listItems(Request $request, string $modelType): JsonResponse
    {
        if (!isset($this->translatableModels[$modelType])) {
            return response()->json(['error' => 'Invalid model type'], 404);
        }

        // Set app locale if provided (for translated attribute access)
        $requestLocale = $request->input('locale');
        if ($requestLocale && in_array($requestLocale, $this->getLocales())) {
            app()->setLocale($requestLocale);
        }

        $config = $this->translatableModels[$modelType];
        $modelClass = $config['model'];
        $locales = $this->getLocales();

        $perPage = $request->input('per_page', 25);
        $search = $request->input('search', '');

        $query = $modelClass::withoutGlobalScopes()->with('translations');

        // Basic search on identifier field
        if ($search) {
            $identifierField = $config['identifier'];
            $query->whereHas('translations', function ($q) use ($identifierField, $search) {
                $q->where($identifierField, 'like', "%{$search}%");
            });
        }

        $items = $query->paginate($perPage);

        $items->getCollection()->transform(function ($item) use ($config, $locales) {
            $translationStatus = [];
            foreach ($locales as $locale) {
                $translationStatus[$locale] = $item->hasTranslation($locale);
            }

            return [
                'id'                 => $item->id,
                'identifier'         => $item->{$config['identifier']}
                    ?? $item->getTranslation('en')?->{$config['identifier']}
                    ?? "#{$item->id}",
                'translation_status' => $translationStatus,
                'created_at'         => $item->created_at,
                'updated_at'         => $item->updated_at,
            ];
        });

        return response()->json([
            'items'   => $items,
            'fields'  => $config['fields'],
            'locales' => $locales,
        ]);
    }

    /**
     * Bulk update translations for multiple items.
     */
    public function bulkUpdate(Request $request, string $modelType): JsonResponse
    {
        if (!isset($this->translatableModels[$modelType])) {
            return response()->json(['error' => 'Invalid model type'], 404);
        }

        $config = $this->translatableModels[$modelType];
        $modelClass = $config['model'];
        $items = $request->input('items', []);

        $updated = 0;
        $errors = [];

        DB::beginTransaction();
        try {
            foreach ($items as $itemData) {
                $item = $modelClass::withoutGlobalScopes()->find($itemData['id']);

                if (!$item) {
                    $errors[] = "Item #{$itemData['id']} not found";
                    continue;
                }

                if (isset($itemData['translations'])) {
                    foreach ($itemData['translations'] as $locale => $fields) {
                        if (!in_array($locale, $this->getLocales())) {
                            continue;
                        }

                        $data = [];
                        foreach ($config['fields'] as $field) {
                            if (array_key_exists($field, $fields)) {
                                $data[$field] = $fields[$field];
                            }
                        }

                        if (!empty($data)) {
                            $item->translateOrNew($locale)->fill($data);
                        }
                    }

                    $item->save();

                    // Sync wiki translations if the model is wikiable
                    $this->syncWikiTranslations($item, $itemData['translations']);

                    $updated++;
                }
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'updated' => $updated,
                'errors'  => $errors,
            ]);
        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json([
                'error'   => 'Failed to update translations',
                'message' => $e->getMessage(),
            ], 500);
        }
    }
}
