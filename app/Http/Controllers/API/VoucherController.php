<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use App\Services\VoucherService;
use App\Http\Responses\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;



/**
 * @group Vouchers
 *
 * APIs for managing vouchers.
 */

class VoucherController extends Controller
{
    protected VoucherService $voucherService;

    public function __construct(VoucherService $voucherService)
    {
        $this->voucherService = $voucherService;
    }

    /**
     * @group Vouchers
     *
     * Create a new voucher.
     *
     * This endpoint allows sponsors to create a new voucher with specified parameters.
     *
     * @bodyParam merchant_id array optional Array of merchant IDs. Example: [1, 2]
     * @bodyParam purpose string optional Purpose of the voucher. Example: "Special Event"
     * @bodyParam voucher_amount number required Total amount of the voucher. Example: 100
     * @bodyParam amount_per_code number required Amount per voucher code. Example: 10
     * @bodyParam expiry_date string optional Expiry date of the voucher in YYYY-MM-DD format. Example: "2024-12-31"
     * @bodyParam limit number optional Limit on the number of uses. Example: 500
     * @bodyParam type string optional Type of voucher (one_time or multiple_time). Example: multiple_time
     * @bodyParam code_generation_method string optional Method for generating codes (sms or qr_code). Example: qr_code
     * @bodyParam location string optional Location where the voucher is valid. Example: "Oyo"
     *
     * @response 201 {
     *     "success": true,
     *     "message": "Voucher created successfully.",
     *     "data": {
     *         "purpose": "Special Event",
     *         "voucher_amount": 100,
     *         "amount_per_code": 10,
     *         "expiry_date": "2024-12-31",
     *         "limit": 500,
     *         "type": "multiple_time",
     *         "code_generation_method": "qr_code",
     *         "location": "Oyo",
     *         "sponsor_id": 1,
     *         "voucher_code": "19AGVCBQOA",
     *         "updated_at": "2024-08-02T17:25:18.000000Z",
     *         "created_at": "2024-08-02T17:25:18.000000Z",
     *         "id": 10,
     *         "merchants": [
     *             {
     *                 "id": 1,
     *                 "user_id": 2,
     *                 "store_name": "olaoluwa store",
     *                 "store_description": "we feed the nation",
     *                 "created_at": "2024-08-02T17:25:18.000000Z",
     *                 "updated_at": "2024-08-02T17:25:18.000000Z",
     *                 "voucher_code": "19AGVCBQOA"
     *             },
     *             {
     *                 "id": 2,
     *                 "user_id": 3,
     *                 "store_name": "olaoluwa store",
     *                 "store_description": "we feed the nation",
     *                 "created_at": "2024-08-02T17:25:18.000000Z",
     *                 "updated_at": "2024-08-02T17:25:18.000000Z",
     *                 "voucher_code": "19AGVCBQOA"
     *             }
     *         ]
     *     }
     * }
     *
     * @response 403 {
     *     "success": false,
     *     "message": "Unauthorized: Only sponsors can create vouchers."
     * }
     *
     * @response 500 {
     *     "success": false,
     *     "message": "Failed to create voucher.",
     *     "error": "Detailed error message"
     * }
     *
     * @example php
     * $response = $client->post('/vouchers', [
     *     'json' => [
     *         'merchant_id' => [1, 2],
     *         'purpose' => 'Special Event',
     *         'voucher_amount' => 100,
     *         'amount_per_code' => 10,
     *         'expiry_date' => '2024-12-31',
     *         'limit' => 500,
     *         'type' => 'multiple_time',
     *         'code_generation_method' => 'qr_code',
     *         'location' => 'Oyo',
     *     ],
     * ]);
     */

    public function store(Request $request): JsonResponse
    {
        // Ensure the authenticated user is of type 'sponsor'
        $user = Auth::user();
        if ($user->usertype !== 'sponsor') {
            return ApiResponse::error('Unauthorized: Only sponsors can create vouchers.', [], 403);
        }

        // Validate the request
        $validated = $request->validate([
            'merchant_id' => 'nullable|array',
            'merchant_id.*' => 'exists:merchants,id',
            'purpose' => 'nullable|string',
            'voucher_amount' => 'required|numeric',
            'amount_per_code' => 'required|numeric',
            'expiry_date' => 'nullable|date',
            'limit' => 'nullable|numeric',
            'type' => 'nullable|in:one_time,multiple_time',
            'code_generation_method' => 'nullable|in:sms,qr_code',
            'location' => 'nullable|string',
        ]);

        // Add sponsor_id to validated data
        $validated['sponsor_id'] = $user->id;

        // Normalize merchant_id to array if it is a single ID
        if (isset($validated['merchant_id']) && !is_array($validated['merchant_id'])) {
            $validated['merchant_id'] = [$validated['merchant_id']];
        }

        try {
            // Pass the validated data to the service
            $voucher = $this->voucherService->createVoucher($validated);

            // Prepare the response with voucher and associated merchants
            $voucherData = $voucher->toArray();
            $voucherData['merchants'] = $voucher->merchants->map(function ($merchant) {
                return [
                    'id' => $merchant->id,
                    'user_id' => $merchant->user_id,
                    'store_name' => $merchant->store_name,
                    'store_description' => $merchant->store_description,
                    'created_at' => $merchant->pivot->created_at,
                    'updated_at' => $merchant->pivot->updated_at,
                    'voucher_code' => $merchant->pivot->voucher_code,
                ];
            });

            return ApiResponse::success('Voucher created successfully.', $voucherData, 201);
        } catch (\Exception $e) {
            return ApiResponse::error('Failed to create voucher.', ['error' => $e->getMessage()], 500);
        }
    }

    /**
     * @group Vouchers
     *
     * Update an existing voucher.
     *
     * This endpoint allows updating the details of a voucher.
     *
     * @urlParam voucherId int required The ID of the voucher to update. Example: 10
     *
     * @bodyParam merchant_id array required List of merchant IDs associated with the voucher. Example: [1]
     * @bodyParam purpose string required Purpose of the voucher. Example: "Special Discount"
     * @bodyParam expiry_date string required Expiry date of the voucher in YYYY-MM-DD format. Example: "2024-12-31"
     * @bodyParam limit number required Limit on the number of uses. Example: 100
     * @bodyParam voucher_amount number required Total amount of the voucher. Example: 50.00
     * @bodyParam amount_per_code number required Amount per voucher code. Example: 5.00
     * @bodyParam type string required Type of voucher (one_time or multiple_time). Example: one_time
     * @bodyParam code_generation_method string required Method for generating codes (qr_code or sms). Example: qr_code
     * @bodyParam location string required Location where the voucher is valid. Example: "Lagos"
     *
     * @response 200 {
     *     "success": true,
     *     "message": "Voucher updated successfully.",
     *     "data": {
     *         "response": {
     *             "id": 10,
     *             "voucher_code": "19AGVCBQOA",
     *             "sponsor_id": 1,
     *             "purpose": "Special Discount",
     *             "expiry_date": "2024-12-31",
     *             "limit": 100,
     *             "voucher_amount": 50.00,
     *             "amount_per_code": 5.00,
     *             "location": "Lagos",
     *             "type": "one_time",
     *             "voucher_status": "unused",
     *             "code_generation_method": "qr_code",
     *             "deleted_at": null,
     *             "created_at": "2024-08-02T17:25:18.000000Z",
     *             "updated_at": "2024-08-02T17:29:05.000000Z",
     *             "merchants": [
     *                 {
     *                     "id": 1,
     *                     "user_id": 2,
     *                     "store_name": "olaoluwa store",
     *                     "store_description": "we feed the nation",
     *                     "voucher_code": null,
     *                     "deleted_at": null,
     *                     "created_at": "2024-08-02T17:05:28.000000Z",
     *                     "updated_at": "2024-08-02T17:05:28.000000Z",
     *                     "pivot": {
     *                         "voucher_id": 10,
     *                         "merchant_id": 1,
     *                         "voucher_code": "19AGVCBQOA",
     *                         "created_at": "2024-08-02T17:25:18.000000Z",
     *                         "updated_at": "2024-08-02T17:29:05.000000Z"
     *                     }
     *                 }
     *             ]
     *         }
     *     }
     * }
     *
     * @response 403 {
     *     "success": false,
     *     "message": "Unauthorized: Only sponsors can update vouchers."
     * }
     *
     * @response 404 {
     *     "success": false,
     *     "message": "Voucher not found."
     * }
     *
     * @response 500 {
     *     "success": false,
     *     "message": "Failed to update voucher.",
     *     "error": "Detailed error message"
     * }
     *
     * @example php
     * $response = $client->post('/update/10/voucher', [
     *     'json' => [
     *         'merchant_id' => [1],
     *         'purpose' => 'Special Discount',
     *         'expiry_date' => '2024-12-31',
     *         'limit' => 100,
     *         'voucher_amount' => 50.00,
     *         'amount_per_code' => 5.00,
     *         'type' => 'one_time',
     *         'code_generation_method' => 'qr_code',
     *         'location' => 'Lagos',
     *     ],
     * ]);
     */

    public function update(Request $request, int $voucherId): JsonResponse
    {
        // Ensure the authenticated user is of type 'sponsor'
        $user = Auth::user();
        if ($user->usertype !== 'sponsor') {
            return ApiResponse::error('Unauthorized: Only sponsors can update vouchers.', [], 403);
        }

        // Validate the request with optional fields
        $validated = $request->validate([
            'merchant_id' => 'nullable|array',
            'merchant_id.*' => 'nullable|exists:merchants,id',
            'purpose' => 'nullable|string',
            'voucher_amount' => 'nullable|numeric',
            'amount_per_code' => 'nullable|numeric',
            'expiry_date' => 'nullable|date',
            'limit' => 'nullable|numeric',
            'type' => 'nullable|in:one_time,multiple_time',
            'code_generation_method' => 'nullable|in:sms,qr_code',
            'location' => 'nullable|string',
        ]);

        // Add sponsor_id to validated data
        $validated['sponsor_id'] = $user->id;

        // Normalize merchant_id to array if it is a single ID
        if (isset($validated['merchant_id']) && !is_array($validated['merchant_id'])) {
            $validated['merchant_id'] = [$validated['merchant_id']];
        }

        try {
            // Call the service to update the voucher
            $voucher = $this->voucherService->updateVoucher($voucherId, $validated);

            return ApiResponse::success('Voucher updated successfully.', $voucher, 200);

        } catch (ModelNotFoundException $e) {
            // Return an error response when the voucher is not found
            return ApiResponse::error('Voucher not found.', ['error' => $e->getMessage()], 404);
        } catch (QueryException $e) {
            // Return an error response for query-related issues
            return ApiResponse::error('Failed to update voucher.', ['error' => $e->getMessage()], 500);
        }
    }


    /**
     * Soft delete a voucher.
     *
     * @param Request $request
     * @param int $VoucherId
     * @return JsonResponse
     */



    /**
     * Soft delete a voucher.
     *
     * @response 200 {
     *     "success": true,
     *     "message": "Voucher revoked successfully."
     * }
     *
     * @response 403 {
     *     "success": false,
     *     "message": "Unauthorized: Only admin sponsors can revoke vouchers."
     * }
     *
     * @response 404 {
     *     "success": false,
     *     "message": "Voucher not found.",
     *     "error": "Detailed error message"
     * }
     *
     * @response 500 {
     *     "success": false,
     *     "message": "Failed to revoke voucher.",
     *     "error": "Detailed error message"
     * }
     */
    public function revoke(Request $request, int $VoucherId): JsonResponse
    {

        $user = Auth::user();
        if ($user->usertype !== 'sponsor') {
            return ApiResponse::error('Unauthorized: Only admin sponsors can revoke vouchers.', [], 403);
        }
        try {
            // Call the service to soft delete the voucher
            $this->voucherService->revokeVoucher($VoucherId);

            return ApiResponse::success('Voucher revoked successfully.', null, 200);
        } catch (ModelNotFoundException $e) {
            return ApiResponse::error('Voucher not found.', ['error' => $e->getMessage()], 404);
        } catch (QueryException $e) {
            return ApiResponse::error('Failed to revoke voucher.', ['error' => $e->getMessage()], 500);
        }
    }


    /**
     * Permanently delete a voucher.
     *
     * @param Request $request
     * @param int $VoucherId
     * @return JsonResponse
     */



    /**
     * Permanently delete a voucher.
     *
     * @response 200 {
     *     "success": true,
     *     "message": "Voucher deleted successfully."
     * }
     *
     * @response 403 {
     *     "success": false,
     *     "message": "Unauthorized: Only admin sponsors can delete vouchers."
     * }
     *
     * @response 404 {
     *     "success": false,
     *     "message": "Voucher not found.",
     *     "error": "Detailed error message"
     * }
     *
     * @response 500 {
     *     "success": false,
     *     "message": "Failed to delete voucher.",
     *     "error": "Detailed error message"
     * }
     */

    public function destroy(Request $request, int $VoucherId): JsonResponse
    {
        $user = Auth::user();
        if ($user->usertype !== 'sponsor') {
            return ApiResponse::error('Unauthorized: Only admin sponsors can delete vouchers.', [], 403);
        }


        try {
            $this->voucherService->deleteVoucher($VoucherId);
            return ApiResponse::success('Voucher deleted successfully.', null, 200);
        } catch (ModelNotFoundException $e) {
            return ApiResponse::error('Voucher not found.', ['error' => $e->getMessage()], 404);

        } catch (QueryException $e) {
            return ApiResponse::error('Failed to revoke voucher.', ['error' => $e->getMessage()], 500);

        }
    }


    /**
     * @group Vouchers
     *
     * Redeem a voucher.
     *
     * This endpoint allows users to redeem a voucher by providing its code.
     *
     * @urlParam voucher_code string required The code of the voucher to redeem. Example: "19AGVCBQOA"
     *
     * @response 200 {
     *     "success": true,
     *     "message": "Voucher redeemed successfully.",
     *     "data": {
     *         "response": null
     *     }
     * }
     *
     * @response 400 {
     *     "success": false,
     *     "message": "Voucher has expired."
     * }
     *
     * @response 400 {
     *     "success": false,
     *     "message": "Voucher has already been used."
     * }
     *
     * @response 400 {
     *     "success": false,
     *     "message": "No more vouchers available."
     * }
     *
     * @response 400 {
     *     "success": false,
     *     "message": "Voucher is not valid in your location."
     * }
     *
     * @response 404 {
     *     "success": false,
     *     "message": "Voucher not found."
     * }
     *
     * @response 500 {
     *     "success": false,
     *     "message": "Failed to redeem voucher.",
     *     "error": "Detailed error message"
     * }
     *
     * @example php
     * $response = $client->post('/vouchers/redeem', [
     *     'json' => [
     *         'voucher_code' => '19AGVCBQOA',
     *     ],
     * ]);
     */

    public function redeem(Request $request): JsonResponse
    {
        // Validate the request
        $validated = $request->validate([
            'voucher_code' => 'required|string',
        ]);

        // Retrieve the beneficiary's IP address
        $ipAddress = $request->ip();

        try {
            // Redeem the voucher
            $result = $this->voucherService->redeemVoucher($validated['voucher_code'], $ipAddress);

            if ($result['success']) {
                return ApiResponse::success('Voucher redeemed successfully.');
            }

            // Determine appropriate status code based on the result message
            $statusCode = match ($result['message']) {
                'Voucher has expired.' => 400,
                'Voucher has already been used.' => 400,
                'No more vouchers available.' => 400,
                'Voucher is not valid in your location.' => 400,
                'Voucher not found.' => 404,
                default => 500,
            };

            return ApiResponse::error($result['message'], [], $statusCode);
        } catch (ModelNotFoundException $e) {
            return ApiResponse::error('Voucher not found.', ['error' => $e->getMessage()], 404);
        } catch (QueryException $e) {
            return ApiResponse::error('Failed to redeem voucher.', ['error' => $e->getMessage()], 500);
        } catch (\Exception $e) {
            return ApiResponse::error('An unexpected error occurred.', ['error' => $e->getMessage()], 500);
        }
    }

    /**
     * Fetch all vouchers created by a sponsor.
     *
     * @queryParam per_page int optional Number of items per page. Example: 15
     *
     * @response 200 {
     *     "success": true,
     *     "message": "Fetched vouchers successfully.",
     *     "data": {
     *         "data": [
     *             {
     *                 "id": 1,
     *                 "voucher_code": "ABC123",
     *                 "voucher_amount": 100.00,
     *                 "created_at": "2024-08-05T00:00:00Z",
     *                 "updated_at": "2024-08-05T00:00:00Z"
     *             }
     *         ],
     *         "current_page": 1,
     *         "last_page": 10,
     *         "per_page": 15,
     *         "total": 150
     *     }
     * }
     *
     * @response 500 {
     *     "success": false,
     *     "message": "Failed to fetch vouchers.",
     *     "error": "Detailed error message"
     * }
     */

    public function getVouchersBySponsor(Request $request): JsonResponse
    {
        $user = Auth::user();
        if ($user->usertype !== 'sponsor') {
            return ApiResponse::error('Unauthorized: Only sponsors can access their vouchers.', [], 403);
        }


        $perPage = $request->input('per_page', 15);

        try {
            $vouchers = $this->voucherService->getVouchersBySponsor($user->id, $perPage);
            return ApiResponse::success('Fetched vouchers successfully.', $vouchers);
        } catch (\Exception $e) {
            return ApiResponse::error('Failed to fetch vouchers.', ['error' => $e->getMessage()], 500);
        }
    }

    /**
     * @group Vouchers
     *
     * Get vouchers by date range.
     *
     * This endpoint allows sponsors to fetch vouchers within a specified date range.
     *
     * @urlParam start_date string required The start date of the date range. Format: YYYY-MM-DD. Example: "2024-01-01"
     * @urlParam end_date string required The end date of the date range. Format: YYYY-MM-DD. Example: "2024-12-31"
     *
     * @response 200 {
     *     "success": true,
     *     "message": "Vouchers fetched successfully.",
     *     "data": [
     *         {
     *             "id": 10,
     *             "voucher_code": "19AGVCBQOA",
     *             "purpose": "Special Event",
     *             "expiry_date": "2024-12-31",
     *             "amount_per_code": 10,
     *             "voucher_amount": 100,
     *             "location": "Oyo",
     *             "type": "multiple_time",
     *             "code_generation_method": "qr_code",
     *             "created_at": "2024-08-02T17:25:18.000000Z",
     *             "updated_at": "2024-08-02T17:25:18.000000Z"
     *         }
     *     ]
     * }
     *
     * @response 403 {
     *     "success": false,
     *     "message": "Unauthorized: Only sponsors can access their vouchers."
     * }
     *
     * @response 500 {
     *     "success": false,
     *     "message": "Failed to fetch vouchers.",
     *     "error": "Detailed error message"
     * }
     *
     * @example php
     * $response = $client->get('/vouchers/date-range', [
     *     'query' => [
     *         'start_date' => '2024-01-01',
     *         'end_date' => '2024-12-31'
     *     ],
     *     'headers' => [
     *         'Authorization' => 'Bearer YOUR_TOKEN_HERE'
     *     ]
     * ]);
     */

    public function getVouchersByDateRange(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'start_date' => 'required|date',
            'end_date' => 'required|date'
        ]);

        $user = Auth::user();
        if ($user->usertype !== 'sponsor') {
            return ApiResponse::error('Unauthorized: Only sponsors can access their vouchers.', [], 403);
        }

        $startDate = $validated['start_date'];
        $endDate = $validated['end_date'];

        try {
            // Fetch vouchers by date range
            $vouchers = $this->voucherService->getVouchersByDateRange($user->id, $startDate, $endDate);

            return ApiResponse::success('Vouchers fetched successfully.', $vouchers);
        } catch (\Exception $e) {
            return ApiResponse::error('Failed to fetch vouchers.', ['error' => $e->getMessage()], 500);
        }
    }


    /**
     * @group Vouchers
     *
     * Get used vouchers.
     *
     * This endpoint retrieves all used vouchers with pagination support.
     *
     * @queryParam per_page int The number of vouchers per page. Default is 15. Example: 15
     *
     * @response 200 {
     *     "success": true,
     *     "message": "Fetched used vouchers successfully.",
     *     "data": [
     *         {
     *             "id": 10,
     *             "voucher_code": "19AGVCBQOA",
     *             "purpose": "Special Event",
     *             "expiry_date": "2024-12-31",
     *             "amount_per_code": 10,
     *             "voucher_amount": 100,
     *             "location": "Oyo",
     *             "type": "multiple_time",
     *             "code_generation_method": "qr_code",
     *             "used_at": "2024-08-02T17:25:18.000000Z"
     *         }
     *     ]
     * }
     *
     * @response 500 {
     *     "success": false,
     *     "message": "Failed to fetch used vouchers.",
     *     "error": "Detailed error message"
     * }
     *
     * @example php
     * $response = $client->get('/vouchers/used', [
     *     'query' => [
     *         'per_page' => 15
     *     ],
     *     'headers' => [
     *         'Authorization' => 'Bearer YOUR_TOKEN_HERE'
     *     ]
     * ]);
     */

    public function getUsedVouchers(Request $request): JsonResponse
    {
        $perPage = $request->input('per_page', 15);

        try {
            $vouchers = $this->voucherService->getUsedVouchers($perPage);
            return ApiResponse::success('Fetched used vouchers successfully.', $vouchers);
        } catch (\Exception $e) {
            return ApiResponse::error('Failed to fetch used vouchers.', ['error' => $e->getMessage()], 500);
        }
    }


    /**
     * @group Vouchers
     *
     * Get redeemed vouchers.
     *
     * This endpoint retrieves all redeemed vouchers with pagination support.
     *
     * @queryParam per_page int The number of vouchers per page. Default is 15. Example: 15
     *
     * @response 200 {
     *     "success": true,
     *     "message": "Fetched redeemed vouchers successfully.",
     *     "data": [
     *         {
     *             "id": 10,
     *             "voucher_code": "19AGVCBQOA",
     *             "purpose": "Special Event",
     *             "expiry_date": "2024-12-31",
     *             "amount_per_code": 10,
     *             "voucher_amount": 100,
     *             "location": "Oyo",
     *             "type": "multiple_time",
     *             "code_generation_method": "qr_code",
     *             "redeemed_at": "2024-08-02T17:25:18.000000Z"
     *         }
     *     ]
     * }
     *
     * @response 500 {
     *     "success": false,
     *     "message": "Failed to fetch redeemed vouchers.",
     *     "error": "Detailed error message"
     * }
     *
     * @example php
     * $response = $client->get('/vouchers/redeemed', [
     *     'query' => [
     *         'per_page' => 15
     *     ],
     *     'headers' => [
     *         'Authorization' => 'Bearer YOUR_TOKEN_HERE'
     *     ]
     * ]);
     */

    public function getRedeemedVouchers(Request $request): JsonResponse
    {
        $perPage = $request->input('per_page', 15);

        try {
            $vouchers = $this->voucherService->getRedeemedVouchers($perPage);
            return ApiResponse::success('Fetched redeemed vouchers successfully.', $vouchers);
        } catch (\Exception $e) {
            return ApiResponse::error('Failed to fetch redeemed vouchers.', ['error' => $e->getMessage()], 500);
        }
    }


    /**
     * @group Vouchers
     *
     * Get beneficiaries with redeemed vouchers.
     *
     * This endpoint retrieves beneficiaries who have redeemed vouchers with pagination support.
     *
     * @queryParam per_page int The number of beneficiaries per page. Default is 15. Example: 15
     *
     * @response 200 {
     *     "success": true,
     *     "message": "Fetched beneficiaries with redeemed vouchers successfully.",
     *     "data": [
     *         {
     *             "id": 1,
     *             "name": "John Doe",
     *             "redeemed_vouchers_count": 5
     *         }
     *     ]
     * }
     *
     * @response 500 {
     *     "success": false,
     *     "message": "Failed to fetch beneficiaries.",
     *     "error": "Detailed error message"
     * }
     *
     * @example php
     * $response = $client->get('/beneficiaries/redeemed', [
     *     'query' => [
     *         'per_page' => 15
     *     ],
     *     'headers' => [
     *         'Authorization' => 'Bearer YOUR_TOKEN_HERE'
     *     ]
     * ]);
     */

    public function getBeneficiariesWithRedeemedVouchers(Request $request): JsonResponse
    {
        $perPage = $request->input('per_page', 15);

        try {
            $beneficiaries = $this->voucherService->getBeneficiariesWithRedeemedVouchers($perPage);
            return ApiResponse::success('Fetched beneficiaries with redeemed vouchers successfully.', $beneficiaries);
        } catch (\Exception $e) {
            return ApiResponse::error('Failed to fetch beneficiaries.', ['error' => $e->getMessage()], 500);
        }
    }


    /**
     * @group Vouchers
     *
     * Get vouchers yet to be redeemed.
     *
     * This endpoint retrieves vouchers that have not yet been redeemed with pagination support.
     *
     * @queryParam per_page int The number of vouchers per page. Default is 15. Example: 15
     *
     * @response 200 {
     *     "success": true,
     *     "message": "Fetched vouchers yet to be redeemed successfully.",
     *     "data": [
     *         {
     *             "id": 10,
     *             "voucher_code": "19AGVCBQOA",
     *             "purpose": "Special Event",
     *             "expiry_date": "2024-12-31",
     *             "amount_per_code": 10,
     *             "voucher_amount": 100,
     *             "location": "Oyo",
     *             "type": "multiple_time",
     *             "code_generation_method": "qr_code",
     *             "created_at": "2024-08-02T17:25:18.000000Z"
     *         }
     *     ]
     * }
     *
     * @response 500 {
     *     "success": false,
     *     "message": "Failed to fetch vouchers yet to be redeemed.",
     *     "error": "Detailed error message"
     * }
     *
     * @example php
     * $response = $client->get('/vouchers/yet-to-be-redeemed', [
     *     'query' => [
     *         'per_page' => 15
     *     ],
     *     'headers' => [
     *         'Authorization' => 'Bearer YOUR_TOKEN_HERE'
     *     ]
     * ]);
     */

    public function getVouchersYetToBeRedeemed(Request $request): JsonResponse
    {
        $perPage = $request->input('per_page', 15);

        try {
            $vouchers = $this->voucherService->getVouchersYetToBeRedeemed($perPage);
            return ApiResponse::success('Fetched vouchers yet to be redeemed successfully.', $vouchers);
        } catch (\Exception $e) {
            return ApiResponse::error('Failed to fetch vouchers yet to be redeemed.', ['error' => $e->getMessage()], 500);
        }
    }



}
