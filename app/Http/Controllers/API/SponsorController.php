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
    protected $sponsorService;

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

    public function getSponsorDetails(Request $request, $sponsorId): JsonResponse
{
    try {
        // Ensure the authenticated user is of type 'sponsor'
        $token = $request->bearerToken();
        $userProfile = $this->getAuthSponsorProfile($token);

        if (!$userProfile) {
            return ApiResponse::error('Failed to fetch user profile from the third-party service.', [], 401);
        }

        if ($userProfile['id'] !== $sponsorId) {
            return ApiResponse::error('Sponsor ID does not match the authenticated user.', [], 403);
        }

        // Fetch sponsor details from the service
        $sponsorDetails = $this->sponsorService->getSponsorDetails($sponsorId, $userProfile);

        return ApiResponse::success('Sponsor details fetched successfully.', $sponsorDetails);
    } catch (ModelNotFoundException $e) {
        return ApiResponse::error('Sponsor not found.', ['error' => $e->getMessage()], 404);
    } catch (\Exception $e) {
        return ApiResponse::error('Failed to fetch sponsor details.', ['error' => $e->getMessage()], 500);
    }
}



    public function fundSponsorsWallet(Request $request, $sponsor_id): JsonResponse
{
    try {
        // Validate the request
        $validated = $request->validate([
            'amount' => 'required|numeric|min:1',
        ]);

        $amount = $validated['amount'];

        // Ensure the authenticated user is of type 'sponsor'
        $token = $request->bearerToken();
        $userProfile = $this->getAuthSponsorProfile($token);

        if (!$userProfile) {
            return ApiResponse::error('Failed to fetch user profile from the third-party service.', [], 401);
        }

        if ($userProfile['id'] !== $sponsor_id) {
            return ApiResponse::error('Sponsor ID does not match the authenticated user.', [], 403);
        }

        // Fund sponsor's wallet via the service
        $result = $this->sponsorService->fundSponsorsWallet($amount, $sponsor_id);

        if ($result['status'] === 'success') {
            return ApiResponse::success('Wallet funded successfully.', $result['data']);
        }

        return ApiResponse::error($result['message'], [], 400);
    } catch (ValidationException $e) {
        return ApiResponse::error('Validation error.', $e->errors(), 422);
    } catch (ModelNotFoundException $e) {
        return ApiResponse::error('Sponsor not found.', ['error' => $e->getMessage()], 404);
    } catch (\Exception $e) {
        return ApiResponse::error('An unexpected error occurred.', ['error' => $e->getMessage()], 500);
    }
}



    //Calling PayThru gateway for transaction response updates
 public function webhookBusinessResponse(Request $request)
 {
  try {
     $productId = env('PayThru_business_productid');
     $response = $request->all();
     $dataEncode = json_encode($response);
     $data = json_decode($dataEncode);
     $modelType = "Sponsor";
 
     Log::info("Starting webhookSponsorResponse");
     Log::info("Starting webhookSponsorResponse", ['data' => $data, 'modelType' => $modelType]);
     if ($data->notificationType == 1) {
         $sponsor = Payment::where('paymentReference', $data->transactionDetails->paymentReference)->first();

         if ($sponsor) {
 
            $sponsor->payThruReference = $data->transactionDetails->payThruReference;
            $sponsor->fiName = $data->transactionDetails->fiName;
            $sponsor->status = $data->transactionDetails->status;
            $sponsor->amount = $data->transactionDetails->amount;
            $sponsor->responseCode = $data->transactionDetails->responseCode;
            $sponsor->paymentMethod = $data->transactionDetails->paymentMethod;
            $sponsor->commission = $data->transactionDetails->commission;
         // Check if residualAmount is negative
         if ($data->transactionDetails->residualAmount < 0) {
            $sponsor->negative_amount = $data->transactionDetails->residualAmount;
         } else {
            $sponsor->negative_amount = 0;
         }
         $sponsor->residualAmount = $data->transactionDetails->residualAmount ?? 0;
             $sponsor->resultCode = $data->transactionDetails->resultCode;
             $sponsor->responseDescription = $data->transactionDetails->responseDescription;
             $sponsor->providedEmail = $data->transactionDetails->customerInfo->providedEmail;
             $sponsor->providedName = $data->transactionDetails->customerInfo->providedName;
             $buisness->remarks = $data->transactionDetails->customerInfo->remarks;
             $buisness->save();
             Log::info("User buisness updated");
         } else {
             Log::info("User buisness not found for payment reference: " . $data->transactionDetails->paymentReference);
         }
 
         http_response_code(200);
 
     } elseif ($data->notificationType == 2) {
       if (isset($data->transactionDetails->transactionReferences[0])) {
           Log::info("Transaction references: " . json_encode($data->transactionDetails->transactionReferences));
       $transactionReferences = $data->transactionDetails->transactionReferences[0];
       Log::info("Transaction references: " .  $transactionReferences);
       $upda = BusinessWithdrawal::where('transactionReferences', $transactionReferences)->first();
       $updatePaybackWithdrawal = BusinessWithdrawal::where([
         'transactionReferences' => $transactionReferences,
         'uniqueId' => $upda->uniqueId
         ])->first();
 
           $product_action = "withdrawal";
           $referral = ReferralSetting::where('status', 'active')
               ->latest('updated_at')
               ->first();
           if ($referral) {
               $this->referral->checkSettingEnquiry($modelType, $product_action);
           }
 
           if ($updatePaybackWithdrawal) {
               $updatePaybackWithdrawal->paymentAmount = $data->transactionDetails->paymentAmount;
               $updatePaybackWithdrawal->recordDateTime = $data->transactionDetails->recordDateTime;
           // Set the status to "success"
               $updatePaybackWithdrawal->status = 'success';
 
               $updatePaybackWithdrawal->save();
 
               Log::info("Business withdrawal updated");
           } else {
               Log::info("Business withdrawal not found for transaction references: " . $data->transactionDetails->transactionReferences[0]);
           }
       } else {
           Log::info("Transaction references not found in the webhook data");
       }
   }
 
   http_response_code(200);
 } catch (\Illuminate\Database\QueryException $e) {
   Log::error($e->getMessage());
   return response()->json(['error' => 'An error occurred'], 500);
 }
 }



    }




