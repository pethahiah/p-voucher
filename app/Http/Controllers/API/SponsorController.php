<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponse;
use App\Services\SponsorService;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SponsorController extends Controller
{
    //
    protected SponsorService $sponsorService;

    public function __construct(SponsorService $sponsorService)
    {
        $this->sponsorService = $sponsorService;
    }


    /**
     * @group Sponsors
     *
     * Get sponsor details.
     *
     * This endpoint retrieves details of a specific sponsor based on their ID.
     *
     * @urlParam sponsorId int required The ID of the sponsor. Example: 1
     *
     * @response 200 {
     *     "success": true,
     *     "message": "Sponsor details fetched successfully.",
     *     "data": {
     *         "id": 1,
     *         "name": "Sponsor Name",
     *         "email": "sponsor@example.com",
     *         "phone": "+1234567890",
     *         "created_at": "2024-01-01T00:00:00.000000Z",
     *         "updated_at": "2024-01-01T00:00:00.000000Z"
     *     }
     * }
     *
     * @response 404 {
     *     "success": false,
     *     "message": "Sponsor not found.",
     *     "error": "Detailed error message"
     * }
     *
     * @response 500 {
     *     "success": false,
     *     "message": "Failed to fetch sponsor details.",
     *     "error": "Detailed error message"
     * }
     *
     * @example php
     * $response = $client->get('/sponsors/1', [
     *     'headers' => [
     *         'Authorization' => 'Bearer YOUR_TOKEN_HERE'
     *     ]
     * ]);
     */

    public function getSponsorDetails(int $sponsorId): JsonResponse
    {
        try {
            // Fetch sponsor details from the service
            $sponsorDetails = $this->sponsorService->getSponsorDetails($sponsorId);
            return ApiResponse::success('Sponsor details fetched successfully.', $sponsorDetails);
        } catch (ModelNotFoundException $e) {
            return ApiResponse::error('Sponsor not found.', ['error' => $e->getMessage()], 404);
        } catch (\Exception $e) {
            return ApiResponse::error('Failed to fetch sponsor details.', ['error' => $e->getMessage()], 500);
        }
    }
}
