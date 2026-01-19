<?php

namespace App\Http\Controllers;

use App\Services\NewsletterService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class NewsletterController extends Controller
{
    protected $newsletterService;

    public function __construct(NewsletterService $newsletterService)
    {
        $this->newsletterService = $newsletterService;
    }

    /**
     * Subscribe to newsletter
     */
    public function subscribe(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'email' => 'required|email',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Invalid email address',
                'errors' => $validator->errors(),
            ], 422);
        }

        try {
            $result = $this->newsletterService->subscribe($request->email);

            if ($result['success']) {
                return response()->json([
                    'message' => $result['message'] ?? 'Successfully subscribed to newsletter!',
                    'success' => true,
                ]);
            }

            return response()->json([
                'message' => $result['message'] ?? 'Failed to subscribe to newsletter',
                'success' => false,
            ], 400);
        } catch (\Exception $e) {
            \Log::error('Newsletter subscription error', [
                'email' => $request->email,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'message' => 'An error occurred while subscribing. Please try again later.',
                'success' => false,
            ], 500);
        }
    }
}
