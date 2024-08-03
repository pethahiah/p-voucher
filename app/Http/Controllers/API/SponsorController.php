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
     * Fetch the sponsor details by Sponsor ID.
     *
     * @param int $sponsorId
     * @return JsonResponse
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
