<?php

namespace App\Http\Controllers;

use App\Models\Menu\Menu;
use App\Models\Menu\MenuItem;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class MenuController extends Controller
{
    /**
     * Create a new controller instance.
     * Admin methods are protected by role check.
     */
    public function __construct()
    {
        // These methods require admin role
        $this->middleware(function ($request, $next) {
            $user = $request->user();
            if (!$user || !$user->hasRole('admin')) {
                return response()->json(['message' => 'Unauthorized - Admin role required'], 403);
            }
            return $next($request);
        })->only(['index', 'store', 'update', 'destroy', 'getItems', 'addItem', 'updateItem', 'deleteItem', 'reorderItems']);
    }

    /**
     * Get menu by location (for frontend consumption).
     */
    public function getByLocation(string $location): JsonResponse
    {
        $menu = Menu::getByLocation($location);

        if (!$menu) {
            return response()->json([
                'items' => [],
            ]);
        }

        $user = Auth::user();
        $items = $this->filterMenuItems($menu->rootItems, $user);

        return response()->json([
            'menu' => $menu->only(['name', 'slug', 'location']),
            'items' => $items,
        ]);
    }

    /**
     * Get menu by slug.
     */
    public function getBySlug(string $slug): JsonResponse
    {
        $menu = Menu::getBySlug($slug);

        if (!$menu) {
            return response()->json(['message' => 'Menu not found'], 404);
        }

        $user = Auth::user();
        $items = $this->filterMenuItems($menu->rootItems, $user);

        return response()->json([
            'menu' => $menu->only(['name', 'slug', 'location', 'description']),
            'items' => $items,
        ]);
    }

    /**
     * Filter menu items based on user permissions.
     */
    protected function filterMenuItems($items, $user): array
    {
        return $items->filter(function ($item) use ($user) {
            return $item->canView($user);
        })->map(function ($item) use ($user) {
            $data = $item->only([
                'id', 'label', 'type', 'href', 'icon', 
                'target', 'order', 'metadata'
            ]);

            // Recursively filter children
            if ($item->children->isNotEmpty()) {
                $data['children'] = $this->filterMenuItems($item->children, $user);
            }

            return $data;
        })->values()->toArray();
    }

    /**
     * Get all menus (admin).
     */
    public function index(): JsonResponse
    {
        $menus = Menu::with(['items' => function ($query) {
            $query->orderBy('order');
        }])->get();

        return response()->json($menus);
    }

    /**
     * Create a new menu.
     */
    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'slug' => 'required|string|max:255|unique:menus,slug',
            'location' => 'nullable|string|max:255',
            'description' => 'nullable|string',
            'is_active' => 'boolean',
        ]);

        $menu = Menu::create($request->all());

        return response()->json([
            'message' => 'Menu created successfully',
            'menu' => $menu,
        ], 201);
    }

    /**
     * Update a menu.
     */
    public function update(Request $request, Menu $menu): JsonResponse
    {
        $request->validate([
            'name' => 'string|max:255',
            'slug' => 'string|max:255|unique:menus,slug,' . $menu->id,
            'location' => 'nullable|string|max:255',
            'description' => 'nullable|string',
            'is_active' => 'boolean',
        ]);

        $menu->update($request->all());

        return response()->json([
            'message' => 'Menu updated successfully',
            'menu' => $menu,
        ]);
    }

    /**
     * Delete a menu.
     */
    public function destroy(Menu $menu): JsonResponse
    {
        $menu->delete();

        return response()->json([
            'message' => 'Menu deleted successfully',
        ]);
    }

    /**
     * Get menu items for a specific menu.
     */
    public function getItems(Menu $menu): JsonResponse
    {
        $items = $menu->items()
            ->with('children')
            ->whereNull('parent_id')
            ->orderBy('order')
            ->get();

        return response()->json($items);
    }

    /**
     * Add item to menu.
     */
    public function addItem(Request $request, Menu $menu): JsonResponse
    {
        $request->validate([
            'label' => 'required|string|max:255',
            'type' => 'required|in:custom,page,route,external',
            'url' => 'nullable|string',
            'route' => 'nullable|string',
            'icon' => 'nullable|string',
            'parent_id' => 'nullable|exists:menu_items,id',
            'target' => 'string|in:_self,_blank',
            'permission' => 'nullable|string',
            'role' => 'nullable|string',
            'auth_required' => 'boolean',
            'guest_only' => 'boolean',
            'order' => 'integer',
            'metadata' => 'nullable|array',
        ]);

        $item = $menu->items()->create($request->all());

        return response()->json([
            'message' => 'Menu item added successfully',
            'item' => $item,
        ], 201);
    }

    /**
     * Update menu item.
     */
    public function updateItem(Request $request, MenuItem $item): JsonResponse
    {
        $request->validate([
            'label' => 'string|max:255',
            'type' => 'in:custom,page,route,external',
            'url' => 'nullable|string',
            'route' => 'nullable|string',
            'icon' => 'nullable|string',
            'parent_id' => 'nullable|exists:menu_items,id',
            'target' => 'string|in:_self,_blank',
            'permission' => 'nullable|string',
            'role' => 'nullable|string',
            'auth_required' => 'boolean',
            'guest_only' => 'boolean',
            'order' => 'integer',
            'is_active' => 'boolean',
            'metadata' => 'nullable|array',
        ]);

        $item->update($request->all());

        return response()->json([
            'message' => 'Menu item updated successfully',
            'item' => $item,
        ]);
    }

    /**
     * Delete menu item.
     */
    public function deleteItem(MenuItem $item): JsonResponse
    {
        $item->delete();

        return response()->json([
            'message' => 'Menu item deleted successfully',
        ]);
    }

    /**
     * Reorder menu items.
     */
    public function reorderItems(Request $request, Menu $menu): JsonResponse
    {
        $request->validate([
            'items' => 'required|array',
            'items.*.id' => 'required|exists:menu_items,id',
            'items.*.order' => 'required|integer',
            'items.*.parent_id' => 'nullable|exists:menu_items,id',
        ]);

        foreach ($request->items as $itemData) {
            MenuItem::where('id', $itemData['id'])
                ->update([
                    'order' => $itemData['order'],
                    'parent_id' => $itemData['parent_id'] ?? null,
                ]);
        }

        return response()->json([
            'message' => 'Menu items reordered successfully',
        ]);
    }
}
