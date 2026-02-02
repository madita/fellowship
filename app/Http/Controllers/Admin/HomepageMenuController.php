<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\HomepageMenuItem;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class HomepageMenuController extends Controller
{
    public function __construct()
    {
        $this->middleware(['auth:sanctum'])->except(['getActiveMenu']);
    }

    /**
     * Get all menu items (admin).
     */
    public function index(): JsonResponse
    {
        $items = HomepageMenuItem::ordered()->get();

        return response()->json([
            'items' => $items,
        ]);
    }

    /**
     * Get active menu items for public rendering.
     */
    public function getActiveMenu(): JsonResponse
    {
        $items = HomepageMenuItem::getActiveItems();

        return response()->json([
            'items' => $items,
        ]);
    }

    /**
     * Create a new menu item.
     */
    public function store(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'label'         => 'required|string',
            'anchor_target' => 'required|string',
            'order'         => 'integer',
            'enabled'       => 'boolean',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed',
                'errors'  => $validator->errors(),
            ], 422);
        }

        $item = HomepageMenuItem::create($request->all());

        return response()->json([
            'message' => 'Menu item created successfully',
            'item'    => $item,
        ], 201);
    }

    /**
     * Update a menu item.
     */
    public function update($id, Request $request): JsonResponse
    {
        $item = HomepageMenuItem::findOrFail($id);

        $validator = Validator::make($request->all(), [
            'label'         => 'string',
            'anchor_target' => 'string',
            'order'         => 'integer',
            'enabled'       => 'boolean',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed',
                'errors'  => $validator->errors(),
            ], 422);
        }

        $item->update($request->all());

        return response()->json([
            'message' => 'Menu item updated successfully',
            'item'    => $item,
        ]);
    }

    /**
     * Delete a menu item.
     */
    public function destroy($id): JsonResponse
    {
        $item = HomepageMenuItem::findOrFail($id);
        $item->delete();

        return response()->json([
            'message' => 'Menu item deleted successfully',
        ]);
    }

    /**
     * Batch update menu item order.
     */
    public function updateOrder(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'items'         => 'required|array',
            'items.*.id'    => 'required|exists:homepage_menu_items,id',
            'items.*.order' => 'required|integer',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed',
                'errors'  => $validator->errors(),
            ], 422);
        }

        foreach ($request->items as $itemData) {
            HomepageMenuItem::where('id', $itemData['id'])
                ->update(['order' => $itemData['order']]);
        }

        HomepageMenuItem::clearCache();

        return response()->json([
            'message' => 'Menu order updated successfully',
        ]);
    }
}
