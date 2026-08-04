<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\FooterSection;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class FooterSectionController extends Controller
{
    public function __construct()
    {
        $this->middleware(['auth:sanctum'])->except(['getActiveSections']);
    }

    /**
     * Get all sections with their widgets (admin).
     */
    public function index()
    {
        $sections = FooterSection::ordered()
            ->with(['widgets' => function ($query) {
                $query->orderBy('order');
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
            'title' => 'nullable|string|max:255',
            'layout' => 'required|string|in:1-col,2-col,3-col,4-col',
            'enabled' => 'boolean',
            'order' => 'integer|min:0',
            'config' => 'nullable|array',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $section = FooterSection::create($validator->validated());

        return response()->json($section, 201);
    }

    /**
     * Update a section.
     */
    public function update(Request $request, $id)
    {
        $section = FooterSection::findOrFail($id);

        $validator = Validator::make($request->all(), [
            'title' => 'nullable|string|max:255',
            'layout' => 'string|in:1-col,2-col,3-col,4-col',
            'enabled' => 'boolean',
            'order' => 'integer|min:0',
            'config' => 'nullable|array',
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
        $section = FooterSection::findOrFail($id);
        $section->delete();

        return response()->json(['message' => __('messages.footer.section_deleted')]);
    }

    /**
     * Toggle section enabled status.
     */
    public function toggle($id)
    {
        $section = FooterSection::findOrFail($id);
        $section->enabled = ! $section->enabled;
        $section->save();

        return response()->json($section);
    }

    /**
     * Update sections order.
     */
    public function updateOrder(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'sections' => 'required|array',
            'sections.*.id' => 'required|exists:sections,id',
            'sections.*.order' => 'required|integer|min:0',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        foreach ($request->sections as $sectionData) {
            FooterSection::where('id', $sectionData['id'])
                ->update(['order' => $sectionData['order']]);
        }

        FooterSection::clearCache();

        return response()->json(['message' => __('messages.footer.sections_reordered')]);
    }

    /**
     * Get active sections with widgets (public).
     */
    public function getActiveSections()
    {
        $sections = FooterSection::getActiveSections();

        return response()->json($sections);
    }
}
