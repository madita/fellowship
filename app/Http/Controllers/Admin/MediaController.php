<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\MediaLibrary;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

class MediaController extends Controller
{
    /**
     * Context labels for human-readable model type names.
     */
    protected array $contextLabels = [
        'App\Models\User' => 'Users',
        'App\Models\Collection' => 'Collections',
        'App\Models\Page' => 'Pages',
        'App\Models\Post' => 'Posts',
        'App\Models\Event' => 'Events',
        'App\Models\MediaLibrary' => 'Media Library',
    ];

    /**
     * Icons for contexts.
     */
    protected array $contextIcons = [
        'App\Models\User' => 'mdi-account',
        'App\Models\Collection' => 'mdi-folder-multiple-image',
        'App\Models\Page' => 'mdi-file-document',
        'App\Models\Post' => 'mdi-post',
        'App\Models\Event' => 'mdi-calendar',
        'App\Models\MediaLibrary' => 'mdi-image-multiple',
    ];

    /**
     * Collection name labels.
     */
    protected array $collectionLabels = [
        'avatars' => 'Avatars',
        'images' => 'Images',
        'gallery' => 'Gallery',
        'default' => 'Default',
    ];

    public function __construct()
    {
        $this->middleware(['auth:sanctum']);
        $this->middleware(['admin']);
    }

    /**
     * Get folder structure for navigation.
     */
    public function folders(): JsonResponse
    {
        // Get all unique model_type + collection_name combinations with counts
        $folders = Media::selectRaw('model_type, collection_name, COUNT(*) as count, SUM(size) as total_size')
            ->groupBy('model_type', 'collection_name')
            ->get();

        // Build hierarchical folder structure
        $structure = [];
        $totalCount = 0;
        $totalSize = 0;

        foreach ($folders as $folder) {
            $modelType = $folder->model_type;
            $collectionName = $folder->collection_name;

            if (! isset($structure[$modelType])) {
                $structure[$modelType] = [
                    'model_type' => $modelType,
                    'label' => $this->contextLabels[$modelType] ?? $this->formatModelType($modelType),
                    'icon' => $this->contextIcons[$modelType] ?? 'mdi-folder',
                    'count' => 0,
                    'total_size' => 0,
                    'collections' => [],
                ];
            }

            $structure[$modelType]['collections'][$collectionName] = [
                'name' => $collectionName,
                'label' => $this->collectionLabels[$collectionName] ?? ucfirst($collectionName),
                'count' => $folder->count,
                'total_size' => $folder->total_size,
                'size_formatted' => $this->formatBytes($folder->total_size),
            ];

            $structure[$modelType]['count'] += $folder->count;
            $structure[$modelType]['total_size'] += $folder->total_size;
            $totalCount += $folder->count;
            $totalSize += $folder->total_size;
        }

        // Format sizes for contexts
        foreach ($structure as &$context) {
            $context['size_formatted'] = $this->formatBytes($context['total_size']);
            $context['collections'] = array_values($context['collections']);
        }

        return response()->json([
            'folders' => array_values($structure),
            'total' => [
                'count' => $totalCount,
                'total_size' => $totalSize,
                'size_formatted' => $this->formatBytes($totalSize),
            ],
        ]);
    }

    /**
     * Get media items within a specific model instance (e.g., a specific user or page).
     */
    public function modelItems(Request $request): JsonResponse
    {
        $modelType = $request->input('model_type');
        $collectionName = $request->input('collection');

        if (! $modelType) {
            return response()->json(['error' => 'model_type is required'], 400);
        }

        // Get unique model instances with their media counts
        $query = Media::selectRaw('model_id, model_type, collection_name, COUNT(*) as count, SUM(size) as total_size, MIN(created_at) as first_upload, MAX(created_at) as last_upload')
            ->where('model_type', $modelType);

        if ($collectionName) {
            $query->where('collection_name', $collectionName);
        }

        $items = $query->groupBy('model_id', 'model_type', 'collection_name')
            ->orderBy('last_upload', 'desc')
            ->get();

        // Enrich with model names
        $enrichedItems = $items->map(function ($item) {
            $modelName = $this->getModelNameById($item->model_type, $item->model_id);

            return [
                'model_id' => $item->model_id,
                'model_type' => $item->model_type,
                'collection_name' => $item->collection_name,
                'model_name' => $modelName,
                'count' => $item->count,
                'total_size' => $item->total_size,
                'size_formatted' => $this->formatBytes($item->total_size),
                'first_upload' => $item->first_upload,
                'last_upload' => $item->last_upload,
            ];
        });

        return response()->json([
            'items' => $enrichedItems,
            'model_type' => $modelType,
            'label' => $this->contextLabels[$modelType] ?? $this->formatModelType($modelType),
            'collection' => $collectionName,
        ]);
    }

    /**
     * List all media with filters.
     */
    public function index(Request $request): JsonResponse
    {
        $query = Media::query();

        // Filter by context (model_type)
        if ($request->filled('context')) {
            $query->where('model_type', $request->input('context'));
        }

        // Filter by model_id (specific model instance)
        if ($request->filled('model_id')) {
            $query->where('model_id', $request->input('model_id'));
        }

        // Filter by collection name
        if ($request->filled('collection')) {
            $query->where('collection_name', $request->input('collection'));
        }

        // Exclude collection (e.g., exclude gallery images from editor picker)
        if ($request->filled('exclude_collection')) {
            $query->where('collection_name', '!=', $request->input('exclude_collection'));
        }

        // Filter by mime type prefix (e.g., 'image', 'application')
        if ($request->filled('mime_type')) {
            $query->where('mime_type', 'like', $request->input('mime_type').'%');
        }

        // Search by file name
        if ($request->filled('search')) {
            $search = $request->input('search');
            $query->where(function ($q) use ($search) {
                $q->where('file_name', 'like', "%{$search}%")
                    ->orWhere('name', 'like', "%{$search}%");
            });
        }

        // Date range filter
        if ($request->filled('date_from')) {
            $query->whereDate('created_at', '>=', $request->input('date_from'));
        }
        if ($request->filled('date_to')) {
            $query->whereDate('created_at', '<=', $request->input('date_to'));
        }

        // Sorting
        $sortBy = $request->input('sort_by', 'created_at');
        $sortDir = $request->input('sort_dir', 'desc');

        // Validate sort column
        $allowedSortColumns = ['created_at', 'size', 'file_name', 'name'];
        if (! in_array($sortBy, $allowedSortColumns)) {
            $sortBy = 'created_at';
        }

        $query->orderBy($sortBy, $sortDir === 'asc' ? 'asc' : 'desc');

        // Pagination
        $perPage = min((int) $request->input('per_page', 24), 100);
        $media = $query->paginate($perPage);

        // Transform the data
        $data = $media->getCollection()->map(function (Media $item) {
            return $this->transformMedia($item);
        });

        return response()->json([
            'data' => $data,
            'meta' => [
                'current_page' => $media->currentPage(),
                'last_page' => $media->lastPage(),
                'per_page' => $media->perPage(),
                'total' => $media->total(),
                'from' => $media->firstItem(),
                'to' => $media->lastItem(),
            ],
        ]);
    }

    /**
     * Get single media details.
     */
    public function show(Media $media): JsonResponse
    {
        return response()->json([
            'data' => $this->transformMedia($media, true),
        ]);
    }

    /**
     * Delete single media.
     */
    public function destroy(Media $media): JsonResponse
    {
        try {
            $media->delete();

            return response()->json([
                'message' => 'Media deleted successfully',
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to delete media',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Bulk delete media.
     */
    public function bulkDestroy(Request $request): JsonResponse
    {
        $request->validate([
            'ids' => 'required|array',
            'ids.*' => 'required|integer|exists:media,id',
        ]);

        try {
            $ids = $request->input('ids');
            $deleted = Media::whereIn('id', $ids)->delete();

            return response()->json([
                'message' => "{$deleted} media items deleted successfully",
                'deleted' => $deleted,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to delete media',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Upload new media to the library.
     */
    public function upload(Request $request): JsonResponse
    {
        $request->validate([
            'files' => 'required|array|min:1',
            'files.*' => 'required|file|mimes:jpeg,jpg,png,gif,webp,svg,pdf|max:10240',
            'collection' => 'nullable|string|in:images,documents',
        ]);

        $library = MediaLibrary::getLibrary();
        $collection = $request->input('collection', 'images');
        $uploaded = [];

        foreach ($request->file('files') as $file) {
            try {
                $media = $library->addMedia($file)
                    ->toMediaCollection($collection);

                $uploaded[] = $this->transformMedia($media);
            } catch (\Exception $e) {
                return response()->json([
                    'message' => 'Failed to upload file: '.$file->getClientOriginalName(),
                    'error' => $e->getMessage(),
                ], 500);
            }
        }

        return response()->json([
            'message' => count($uploaded).' file(s) uploaded successfully',
            'data' => $uploaded,
        ]);
    }

    /**
     * Get library images for selection (used when creating pages).
     */
    public function libraryImages(Request $request): JsonResponse
    {
        $perPage = min((int) $request->input('per_page', 24), 100);
        $search = $request->input('search');

        $query = Media::where('model_type', MediaLibrary::class);

        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('file_name', 'like', "%{$search}%")
                    ->orWhere('name', 'like', "%{$search}%");
            });
        }

        $media = $query->orderBy('created_at', 'desc')->paginate($perPage);

        $data = $media->getCollection()->map(function (Media $item) {
            return [
                'id' => $item->id,
                'uuid' => $item->uuid,
                'file_name' => $item->file_name,
                'name' => $item->name,
                'mime_type' => $item->mime_type,
                'url' => $item->getUrl(),
                'thumb_url' => $this->getThumbUrl($item),
            ];
        });

        return response()->json([
            'data' => $data,
            'meta' => [
                'current_page' => $media->currentPage(),
                'last_page' => $media->lastPage(),
                'per_page' => $media->perPage(),
                'total' => $media->total(),
            ],
        ]);
    }

    /**
     * Get media statistics.
     */
    public function stats(): JsonResponse
    {
        $totalCount = Media::count();
        $totalSize = Media::sum('size');

        // Breakdown by context (model_type)
        $byContext = Media::selectRaw('model_type, COUNT(*) as count, SUM(size) as total_size')
            ->groupBy('model_type')
            ->get()
            ->map(function ($item) {
                return [
                    'model_type' => $item->model_type,
                    'label' => $this->contextLabels[$item->model_type] ?? $this->formatModelType($item->model_type),
                    'count' => $item->count,
                    'total_size' => $item->total_size,
                    'size_formatted' => $this->formatBytes($item->total_size),
                ];
            });

        // Breakdown by file type
        $byType = Media::selectRaw("
                CASE
                    WHEN mime_type LIKE 'image/%' THEN 'Images'
                    WHEN mime_type LIKE 'video/%' THEN 'Videos'
                    WHEN mime_type LIKE 'audio/%' THEN 'Audio'
                    WHEN mime_type LIKE 'application/pdf' THEN 'PDFs'
                    WHEN mime_type LIKE 'application/%' THEN 'Documents'
                    ELSE 'Other'
                END as file_type,
                COUNT(*) as count,
                SUM(size) as total_size
            ")
            ->groupBy('file_type')
            ->get()
            ->map(function ($item) {
                return [
                    'type' => $item->file_type,
                    'count' => $item->count,
                    'total_size' => $item->total_size,
                    'size_formatted' => $this->formatBytes($item->total_size),
                ];
            });

        // Breakdown by collection
        $byCollection = Media::selectRaw('collection_name, COUNT(*) as count, SUM(size) as total_size')
            ->groupBy('collection_name')
            ->get()
            ->map(function ($item) {
                return [
                    'collection' => $item->collection_name,
                    'count' => $item->count,
                    'total_size' => $item->total_size,
                    'size_formatted' => $this->formatBytes($item->total_size),
                ];
            });

        return response()->json([
            'total_count' => $totalCount,
            'total_size' => $totalSize,
            'size_formatted' => $this->formatBytes($totalSize),
            'by_context' => $byContext,
            'by_type' => $byType,
            'by_collection' => $byCollection,
        ]);
    }

    /**
     * Get available contexts (model types).
     */
    public function contexts(): JsonResponse
    {
        $contexts = Media::select('model_type')
            ->distinct()
            ->get()
            ->map(function ($item) {
                return [
                    'value' => $item->model_type,
                    'label' => $this->contextLabels[$item->model_type] ?? $this->formatModelType($item->model_type),
                ];
            });

        // Get available collections
        $collections = Media::select('collection_name')
            ->distinct()
            ->pluck('collection_name')
            ->map(function ($name) {
                return [
                    'value' => $name,
                    'label' => ucfirst(str_replace(['_', '-'], ' ', $name)),
                ];
            });

        return response()->json([
            'contexts' => $contexts,
            'collections' => $collections,
        ]);
    }

    /**
     * Transform a media item for the response.
     */
    protected function transformMedia(Media $media, bool $detailed = false): array
    {
        $data = [
            'id' => $media->id,
            'uuid' => $media->uuid,
            'file_name' => $media->file_name,
            'name' => $media->name,
            'mime_type' => $media->mime_type,
            'size' => $media->size,
            'size_formatted' => $this->formatBytes($media->size),
            'url' => $media->getUrl(),
            'thumb_url' => $this->getThumbUrl($media),
            'collection_name' => $media->collection_name,
            'model_type' => $media->model_type,
            'model_type_label' => $this->contextLabels[$media->model_type] ?? $this->formatModelType($media->model_type),
            'model_id' => $media->model_id,
            'created_at' => $media->created_at?->toIso8601String(),
        ];

        // Add model name if possible
        $data['model_name'] = $this->getModelName($media);

        if ($detailed) {
            $data['custom_properties'] = $media->custom_properties;
            $data['generated_conversions'] = $media->generated_conversions;
            $data['disk'] = $media->disk;
            $data['updated_at'] = $media->updated_at?->toIso8601String();

            // Try to get image dimensions
            if (str_starts_with($media->mime_type, 'image/')) {
                try {
                    $path = $media->getPath();
                    if (file_exists($path)) {
                        $imageInfo = @getimagesize($path);
                        if ($imageInfo) {
                            $data['width'] = $imageInfo[0];
                            $data['height'] = $imageInfo[1];
                        }
                    }
                } catch (\Exception $e) {
                    // Ignore dimension errors
                }
            }
        }

        return $data;
    }

    /**
     * Get thumbnail URL for media.
     */
    protected function getThumbUrl(Media $media): ?string
    {
        try {
            if ($media->hasGeneratedConversion('thumb')) {
                return $media->getUrl('thumb');
            }
        } catch (\Exception $e) {
            // Ignore conversion errors
        }

        // Return main URL for images, null for others
        if (str_starts_with($media->mime_type, 'image/')) {
            return $media->getUrl();
        }

        return null;
    }

    /**
     * Get the name of the associated model.
     */
    protected function getModelName(Media $media): ?string
    {
        return $this->getModelNameById($media->model_type, $media->model_id);
    }

    /**
     * Get model name by type and id.
     */
    protected function getModelNameById(string $modelType, int $modelId): ?string
    {
        try {
            if (! class_exists($modelType)) {
                return "#{$modelId}";
            }

            $model = $modelType::find($modelId);
            if (! $model) {
                return "#{$modelId} (deleted)";
            }

            // Try common name attributes
            foreach (['name', 'title', 'username', 'email'] as $attribute) {
                if (isset($model->{$attribute}) && ! empty($model->{$attribute})) {
                    return $model->{$attribute};
                }
            }

            return "#{$modelId}";
        } catch (\Exception $e) {
            return "#{$modelId}";
        }
    }

    /**
     * Format model type to human-readable string.
     */
    protected function formatModelType(string $modelType): string
    {
        // Get class basename
        $basename = class_basename($modelType);

        // Convert camelCase/PascalCase to words
        return preg_replace('/([a-z])([A-Z])/', '$1 $2', $basename);
    }

    /**
     * Format bytes to human-readable string.
     */
    protected function formatBytes(int $bytes, int $precision = 2): string
    {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];

        $bytes = max($bytes, 0);
        $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
        $pow = min($pow, count($units) - 1);

        $bytes /= (1 << (10 * $pow));

        return round($bytes, $precision).' '.$units[$pow];
    }
}
