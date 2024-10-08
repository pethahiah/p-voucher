<?php

namespace App\Services;

use App\Models\Sponsor;
use Illuminate\Database\Eloquent\ModelNotFoundException;

class SponsorService
{

    public function getSponsorDetails(int $sponsorId): array
    {
        // Find the sponsor by ID and load associated user details
        $sponsor = Sponsor::with('user')->findOrFail($sponsorId);

        // Convert the sponsor and user models to array
        return [
            'sponsor' => $sponsor->toArray(),
            'user' => $sponsor->user ? $sponsor->user->toArray() : null
        ];
    }
}
