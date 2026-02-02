<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Section;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class HomepageSectionController extends Controller
{
    /**
     * Get all sections with their widgets (admin).
     */
    public function index()
    {
        $sections = Section::ordered()
            ->with(['widgets' => function ($query) {
                $query->orderBy('column')->orderBy('order');
            }])
            ->get();

        return response()->json($sections);
    }

    /**
     * Create a new section.
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'title'     => 'nullable|string|max:255',
            'layout'    => 'required|string|in:1-col,2-col,3-col,4-col,2-1-col,1-2-col',
            'enabled'   => 'boolean',
            'order'     => 'integer|min:0',
            'config'    => 'nullable|array',
            'anchor_id' => 'nullable|string|max:255|unique:sections,anchor_id|regex:/^[a-z0-9-]+$/',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $section = Section::create($validator->validated());

        return response()->json($section, 201);
    }

    /**
     * Update a section.
     */
    public function update(Request $request, $id)
    {
        $section = Section::findOrFail($id);

        $validator = Validator::make($request->all(), [
            'title'     => 'nullable|string|max:255',
            'layout'    => 'string|in:1-col,2-col,3-col,4-col,2-1-col,1-2-col',
            'enabled'   => 'boolean',
            'order'     => 'integer|min:0',
            'config'    => 'nullable|array',
            'anchor_id' => 'nullable|string|max:255|unique:sections,anchor_id,'.$id.'|regex:/^[a-z0-9-]+$/',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $section->update($validator->validated());

        return response()->json($section);
    }

    /**
     * Delete a section.
     */
    public function destroy($id)
    {
        $section = Section::findOrFail($id);
        $section->delete();

        return response()->json(['message' => 'Section deleted successfully']);
    }

    /**
     * Toggle section enabled status.
     */
    public function toggle($id)
    {
        $section = Section::findOrFail($id);
        $section->enabled = !$section->enabled;
        $section->save();

        return response()->json($section);
    }

    /**
     * Update sections order.
     */
    public function updateOrder(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'sections'         => 'required|array',
            'sections.*.id'    => 'required|exists:sections,id',
            'sections.*.order' => 'required|integer|min:0',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        foreach ($request->sections as $sectionData) {
            Section::where('id', $sectionData['id'])
                ->update(['order' => $sectionData['order']]);
        }

        Section::clearCache();

        return response()->json(['message' => 'Sections reordered successfully']);
    }

    /**
     * Get active sections with widgets (public).
     */
    public function getActiveSections()
    {
        $sections = Section::getActiveSections();

        return response()->json($sections);
    }
}
