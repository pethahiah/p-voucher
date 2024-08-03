<?php

namespace App\Services;

use App\Models\Merchant;
use Illuminate\Database\Eloquent\ModelNotFoundException;

class MerchantService
{


    /**
     * Fetch the merchant profile by ID.
     *
     * @param int $merchantId
     * @return array
     * @throws ModelNotFoundException
     */
    public function getMerchantProfile(int $merchantId): array
    {
        // Find the merchant by ID with related user details
        $merchant = Merchant::with('user')->findOrFail($merchantId);

        // Convert the entire model with relationships to an array
        return [
            'merchant' => $merchant->toArray(),
            'user' => $merchant->user->toArray()
        ];
    }

}
