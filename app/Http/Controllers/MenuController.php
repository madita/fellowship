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
     * Public read endpoints are open; write/admin endpoints require the
     * project's standard admin middleware.
     */
    public function __construct()
    {
        $this->middleware('admin')->only([
            'index', 'store', 'update', 'destroy',
            'getItems', 'addItem', 'updateItem', 'deleteItem', 'reorderItems',
        ]);
    }

    /**
     * Get menu by location (for frontend consumption).
     */
    public function getByLocation(string $location): JsonResponse
    {
        $menu = Menu::getByLocation($location);

        if (! $menu) {
            return response()->json([
                'items' => [],
            ]);
        }

        return response()->json([
            'menu' => $menu->only(['name', 'slug', 'location']),
            'items' => $this->filterMenuItems($menu->rootItems, Auth::user()),
        ]);
    }

    /**
     * Get menu by slug.
     */
    public function getBySlug(string $slug): JsonResponse
    {
        $menu = Menu::getBySlug($slug);

        if (! $menu) {
            return response()->json(['message' => 'Menu not found'], 404);
        }

        return response()->json([
            'menu' => $menu->only(['name', 'slug', 'location', 'description']),
            'items' => $this->filterMenuItems($menu->rootItems, Auth::user()),
        ]);
    }

    /**
     * Recursively filter menu items by per-item visibility rules.
     */
    protected function filterMenuItems($items, $user): array
    {
        return $items
            ->filter(fn ($item) => $item->canView($user))
            ->map(function ($item) use ($user) {
                $data = $item->only([
                    'id', 'label', 'type', 'href', 'icon',
                    'target', 'order', 'metadata',
                ]);

                if ($item->children->isNotEmpty()) {
                    $data['children'] = $this->filterMenuItems($item->children, $user);
                }

                return $data;
            })
            ->values()
            ->toArray();
    }

    /**
     * Get all menus (admin).
     */
    public function index(): JsonResponse
    {
        $menus = Menu::with(['items' => fn ($q) => $q->orderBy('order')])->get();

        return response()->json($menus);
    }

    /**
     * Create a new menu.
     */
    public function store(Request $request): JsonResponse
    {
        $request->validate($this->menuRules());

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
        $request->validate($this->menuRules($menu));

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
        $request->validate($this->itemRules(creating: true));

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
        $request->validate($this->itemRules(creating: false));

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
            MenuItem::where('id', $itemData['id'])->update([
                'order' => $itemData['order'],
                'parent_id' => $itemData['parent_id'] ?? null,
            ]);
        }

        return response()->json([
            'message' => 'Menu items reordered successfully',
        ]);
    }

    /**
     * Validation rules for a Menu. Pass the existing menu for an update
     * (so the slug-unique rule can ignore it).
     */
    private function menuRules(?Menu $menu = null): array
    {
        $slug = 'string|max:255|unique:menus,slug'.($menu ? ','.$menu->id : '');

        return [
            'name' => ($menu ? '' : 'required|').'string|max:255',
            'slug' => ($menu ? '' : 'required|').$slug,
            'location' => 'nullable|string|max:255',
            'description' => 'nullable|string',
            'is_active' => 'boolean',
        ];
    }

    /**
     * Validation rules for a MenuItem. `label` and `type` are required only
     * on create.
     */
    private function itemRules(bool $creating): array
    {
        $req = $creating ? 'required|' : '';

        return [
            'label' => $req.'string|max:255',
            'type' => $req.'in:custom,page,route,external',
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
        ];
    }
}
