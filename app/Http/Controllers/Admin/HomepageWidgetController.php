<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Widget;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class HomepageWidgetController extends Controller
{
    public function __construct()
    {
        $this->middleware(['auth:sanctum'])->except(['getActiveWidgets']);
    }

    /**
     * Get all widgets (admin)
     */
    public function index(): JsonResponse
    {
        $widgets = Widget::ordered()->get();

        return response()->json([
            'widgets' => $widgets,
        ]);
    }

    /**
     * Get active widgets for public homepage rendering
     */
    public function getActiveWidgets(): JsonResponse
    {
        $widgets = Widget::getActiveWidgets();

        return response()->json([
            'widgets' => $widgets,
        ]);
    }

    /**
     * Create a new widget
     */
    public function store(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'section_id' => 'nullable|exists:homepage_sections,id',
            'type' => 'required|string',
            'title' => 'nullable|string',
            'enabled' => 'boolean',
            'order' => 'integer',
            'column' => 'integer|min:1',
            'content' => 'required|array',
            'config' => 'nullable|array',
            'anchor_id' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        $widget = Widget::create($request->all());

        return response()->json([
            'message' => 'Widget created successfully',
            'widget' => $widget,
        ], 201);
    }

    /**
     * Update a widget
     */
    public function update($id, Request $request): JsonResponse
    {
        $widget = Widget::findOrFail($id);

        $validator = Validator::make($request->all(), [
            'section_id' => 'nullable|exists:homepage_sections,id',
            'type' => 'string',
            'title' => 'nullable|string',
            'enabled' => 'boolean',
            'order' => 'integer',
            'column' => 'integer|min:1',
            'content' => 'array',
            'config' => 'nullable|array',
            'anchor_id' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        \Log::info('Updating widget', [
            'id' => $id,
            'content' => $request->input('content'),
            'all_data' => $request->all()
        ]);

        $widget->update($request->all());

        \Log::info('Widget updated', [
            'id' => $id,
            'saved_content' => $widget->fresh()->content
        ]);

        return response()->json([
            'message' => 'Widget updated successfully',
            'widget' => $widget->fresh(),
        ]);
    }

    /**
     * Delete a widget
     */
    public function destroy($id): JsonResponse
    {
        $widget = Widget::findOrFail($id);
        $widget->delete();

        return response()->json([
            'message' => 'Widget deleted successfully',
        ]);
    }

    /**
     * Toggle widget enabled status
     */
    public function toggle($id): JsonResponse
    {
        $widget = Widget::findOrFail($id);
        $widget->enabled = !$widget->enabled;
        $widget->save();

        return response()->json([
            'message' => 'Widget toggled successfully',
            'widget' => $widget,
        ]);
    }

    /**
     * Duplicate a widget
     */
    public function duplicate($id): JsonResponse
    {
        $widget = Widget::findOrFail($id);

        $newWidget = $widget->replicate();
        $newWidget->title = ($widget->title ?? $widget->type) . ' (Copy)';
        $newWidget->order = Widget::max('order') + 1;
        $newWidget->enabled = false;
        $newWidget->save();

        return response()->json([
            'message' => 'Widget duplicated successfully',
            'widget' => $newWidget,
        ], 201);
    }

    /**
     * Batch update widget order
     */
    public function updateOrder(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'widgets' => 'required|array',
            'widgets.*.id' => 'required|exists:widgets,id',
            'widgets.*.order' => 'required|integer',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        foreach ($request->widgets as $widgetData) {
            Widget::where('id', $widgetData['id'])
                ->update(['order' => $widgetData['order']]);
        }

        Widget::clearCache();

        return response()->json([
            'message' => 'Widget order updated successfully',
        ]);
    }
}
