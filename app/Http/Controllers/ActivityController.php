<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Spatie\Activitylog\Models\Activity;

class ActivityController extends Controller
{
    /**
     * Get paginated activity feed, optionally filtered by log name.
     */
    public function index(Request $request): JsonResponse
    {
        $query = Activity::with(['causer', 'subject'])
            ->orderByDesc('created_at');

        if ($request->has('log')) {
            $query->where('log_name', $request->query('log'));
        }

        return response()->json($query->paginate(20));
    }
}
