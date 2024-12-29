<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use App\Models\Payment;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Exception;
use App\Models\Sponsor;
use App\Services\PaythruService;
use Illuminate\Database\Eloquent\ModelNotFoundException;

class SponsorService
{

    protected $paythruService;

    public function __construct(PaythruService $paythruService)
    {
        $this->paythruService = $paythruService;
    }

   public function getSponsorDetails($sponsorId, array $userProfile): array
        {
            // Find the sponsor by ID and load associated user details
            $sponsor = Sponsor::findOrFail($sponsorId);
        
            return [
                'sponsor' => $sponsor->toArray(),
                'user' => $userProfile,
            ];
        }


    public function fundSponsorsWallet($amount, $sponsor_id)
    {
        try {
            $currentTimestamp = now();
            $prodUrl = env('PayThru_Base_Live_Url');
            $productId = env('PayThru_business_productid');
            $secret = env('PayThru_App_Secret');

            // Create hash signature
            $hashSign = hash('sha512', $amount . $secret);
            $token = $this->paythruService->handle();
            $description = "Mpos payment option";

            $data = [
                'amount' => $amount,
                'productId' => $productId,
                'transactionReference' => time() . $amount,
                'paymentDescription' => $description,
                'paymentType' => 1,
                'sign' => $hashSign,
                'displaySummary' => false,
            ];

            $url = $prodUrl . '/transaction/create';

            // API call to external service
            $response = Http::withHeaders([
                'Content-Type' => 'application/json',
                'Authorization' => $token,
            ])->post($url, $data);

            if ($response->failed()) {
                return ['status' => 'error', 'message' => 'Transaction failed.'];
            }

            $transaction = json_decode($response->body(), true);

            if (!$transaction['successful']) {
                return ['status' => 'error', 'message' => 'Whoops! ' . $transaction['message']];
            }

            $paylink = $transaction['payLink'];

            // Record payment details in the database
            if ($paylink) {
                $lastSegment = basename($paylink);

                $payment = Payment::create([
                    'transaction_amount' => $amount,
                    'sponsor_id' => Auth::user()->id,
                    'description' => $description,
                    'paymentReference' => $lastSegment,
                    'unique_code' => $this->generateUniqueCode(),
                ]);

                // Retrieve sponsor details
                $sponsor = Sponsor::where('sponsor_id', $sponsor_id)->first();

                if ($sponsor) {
                    return [
                        'status' => 'success',
                        'data' => [
                            'sponsor_name' => $sponsor->sponsor_name,
                            'sponsor_registration_number' => $sponsor->sponsor_registration_number,
                            'transaction_amount' => $amount,
                            'created_at' => $payment->created_at,
                            'pay_link' => $paylink,
                        ]
                    ];
                } else {
                    return ['status' => 'error', 'message' => 'Sponsor not found.'];
                }
            }
        } catch (Exception $e) {
            Log::error($e->getMessage());
            return ['status' => 'error', 'message' => 'Unexpected error occurred.'];
        }
    }

    private function generateUniqueCode()
    {
        return strtoupper(uniqid('PAY'));
    }


}
