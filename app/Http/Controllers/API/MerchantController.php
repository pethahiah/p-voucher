<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponse;
use App\Services\MerchantService;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MerchantController extends Controller
{
    protected MerchantService $merchantService;

    public function __construct(MerchantService $merchantService)
    {
        $this->merchantService = $merchantService;
    }


    /**
     * @group Merchants
     *
     * Get merchant profile.
     *
     * This endpoint retrieves the profile details of a specific merchant based on their ID.
     *
     * @urlParam merchantId int required The ID of the merchant. Example: 1
     *
     * @response 200 {
     *     "success": true,
     *     "message": "Merchant profile fetched successfully.",
     *     "data": {
     *         "id": 1,
     *         "user_id": 2,
     *         "store_name": "Merchant Store",
     *         "store_description": "A description of the store.",
     *         "location": "City",
     *         "contact_email": "merchant@example.com",
     *         "contact_phone": "+1234567890",
     *         "created_at": "2024-01-01T00:00:00.000000Z",
     *         "updated_at": "2024-01-01T00:00:00.000000Z"
     *     }
     * }
     *
     * @response 404 {
     *     "success": false,
     *     "message": "Merchant not found.",
     *     "error": "Detailed error message"
     * }
     *
     * @response 500 {
     *     "success": false,
     *     "message": "Failed to fetch merchant profile.",
     *     "error": "Detailed error message"
     * }
     *
     * @example php
     * $response = $client->get('/merchants/1', [
     *     'headers' => [
     *         'Authorization' => 'Bearer YOUR_TOKEN_HERE'
     *     ]
     * ]);
     */

    public function getMerchantProfile(int $merchantId): JsonResponse
    {
        try {
            // Fetch merchant profile from the service
            $merchantProfile = $this->merchantService->getMerchantProfile($merchantId);
            return ApiResponse::success('Merchant profile fetched successfully.', $merchantProfile);
        } catch (ModelNotFoundException $e) {
            return ApiResponse::error('Merchant not found.', ['error' => $e->getMessage()], 404);
        } catch (\Exception $e) {
            return ApiResponse::error('Failed to fetch merchant profile.', ['error' => $e->getMessage()], 500);
        }
    }
}
