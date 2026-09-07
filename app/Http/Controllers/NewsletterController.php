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
     * Subscribe to newsletter.
     */
    public function subscribe(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'email' => 'required|email',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => __('messages.newsletter.invalid_email'),
                'errors'  => $validator->errors(),
            ], 422);
        }

        try {
            $result = $this->newsletterService->subscribe($request->email);

            if ($result['success']) {
                return response()->json([
                    'message' => $result['message'] ?? __('messages.newsletter.subscribed'),
                    'success' => true,
                ]);
            }

            return response()->json([
                'message' => $result['message'] ?? __('messages.newsletter.subscribe_failed'),
                'success' => false,
            ], 400);
        } catch (\Exception $e) {
            \Log::error('Newsletter subscription error', [
                'email' => $request->email,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'message' => __('messages.newsletter.error_occurred'),
                'success' => false,
            ], 500);
        }
    }
}
