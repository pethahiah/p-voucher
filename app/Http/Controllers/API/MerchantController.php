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
     * Fetch the merchant profile by ID.
     *
     * @param int $merchantId
     * @return JsonResponse
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
