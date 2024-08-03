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

class VoucherController extends Controller
{
    protected VoucherService $voucherService;

    public function __construct(VoucherService $voucherService)
    {
        $this->voucherService = $voucherService;
    }


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
     * Redeem a voucher.
     *
     * @param Request $request
     * @return JsonResponse
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
     * Fetch all vouchers created by a sponsor with pagination.
     *
     * @param Request $request
     * @return JsonResponse
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
     * Fetch vouchers by date range.
     *
     * @param Request $request
     * @return JsonResponse
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
     * Fetch all used vouchers with pagination.
     *
     * @param Request $request
     * @return JsonResponse
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
     * Fetch all redeemed vouchers with pagination.
     *
     * @param Request $request
     * @return JsonResponse
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
     * Fetch all beneficiaries who redeemed vouchers with pagination.
     *
     * @param Request $request
     * @return JsonResponse
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
     * Fetch all vouchers that are yet to be redeemed with pagination.
     *
     * @param Request $request
     * @return JsonResponse
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
