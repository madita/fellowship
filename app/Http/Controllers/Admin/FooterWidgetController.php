<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\FooterWidget;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class FooterWidgetController extends Controller
{
    public function __construct()
    {
        $this->middleware(['auth:sanctum'])->except(['getActiveWidgets']);
    }

    /**
     * Get all widgets (admin).
     */
    public function index(): JsonResponse
    {
        $widgets = FooterWidget::ordered()->get();

        return response()->json([
            'widgets' => $widgets,
        ]);
    }

    /**
     * Get active widgets for public footer rendering.
     */
    public function getActiveWidgets(): JsonResponse
    {
        $widgets = FooterWidget::getActiveWidgets();

        return response()->json([
            'widgets' => $widgets,
        ]);
    }

    /**
     * Create a new widget.
     */
    public function store(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'type'       => 'required|string|in:quicklinks,menu,contact,newsletter,social,text',
            'config'     => 'required|array',
            'order'      => 'integer',
            'enabled'    => 'boolean',
            'section_id' => 'nullable|exists:sections,id',
            'column'     => 'nullable|integer|min:1|max:4',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => __('messages.error.validation'),
                'errors'  => $validator->errors(),
            ], 422);
        }

        // Set default order if not provided
        $data = $validator->validated();
        if (!isset($data['order'])) {
            $data['order'] = FooterWidget::max('order') + 1;
        }

        // Log what we're creating
        \Log::info('Creating footer widget', [
            'data'       => $data,
            'section_id' => $data['section_id'] ?? 'null',
            'column'     => $data['column'] ?? 'null',
        ]);

        $widget = FooterWidget::create($data);

        // Log what was actually created
        \Log::info('Footer widget created', [
            'id'          => $widget->id,
            'section_id'  => $widget->section_id,
            'column'      => $widget->column,
            'column_type' => gettype($widget->column),
        ]);

        return response()->json([
            'message' => __('messages.footer.widget_created'),
            'widget'  => $widget,
        ], 201);
    }

    /**
     * Update a widget.
     */
    public function update($id, Request $request): JsonResponse
    {
        $widget = FooterWidget::findOrFail($id);

        $validator = Validator::make($request->all(), [
            'type'       => 'string|in:quicklinks,menu,contact,newsletter,social,text',
            'config'     => 'array',
            'order'      => 'integer',
            'enabled'    => 'boolean',
            'section_id' => 'nullable|exists:sections,id',
            'column'     => 'nullable|integer|min:1|max:4',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => __('messages.error.validation'),
                'errors'  => $validator->errors(),
            ], 422);
        }

        $widget->update($validator->validated());

        return response()->json([
            'message' => __('messages.footer.widget_updated'),
            'widget'  => $widget->fresh(),
        ]);
    }

    /**
     * Delete a widget.
     */
    public function destroy($id): JsonResponse
    {
        $widget = FooterWidget::findOrFail($id);
        $widget->delete();

        return response()->json([
            'message' => __('messages.footer.widget_deleted'),
        ]);
    }

    /**
     * Toggle widget active status.
     */
    public function toggle($id): JsonResponse
    {
        $widget = FooterWidget::findOrFail($id);
        $widget->enabled = !$widget->enabled;
        $widget->save();

        return response()->json([
            'message' => __('messages.footer.widget_toggled'),
            'widget'  => $widget,
        ]);
    }

    /**
     * Batch update widget order.
     */
    public function updateOrder(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'widgets'         => 'required|array',
            'widgets.*.id'    => 'required|exists:widgets,id',
            'widgets.*.order' => 'required|integer',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => __('messages.error.validation'),
                'errors'  => $validator->errors(),
            ], 422);
        }

        foreach ($request->widgets as $widgetData) {
            FooterWidget::where('id', $widgetData['id'])
                ->update(['order' => $widgetData['order']]);
        }

        FooterWidget::clearCache();

        return response()->json([
            'message' => __('messages.footer.widget_reordered'),
        ]);
    }
}
