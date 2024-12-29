<?php

namespace App\Traits;

use Exception;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Carbon;


trait GetAuthUserProfileTrait
{
    /**
     * Make an API request to get user profiles.
     *
     * @throws Exception
     */
    private function fetchProfiles($token): array
    {
        $response = Http::withHeaders([
            'Authorization' => 'Bearer ' . $token,
        ])->get('https://api.azatme.com/api/getProfile');

        $apiResponse = json_decode($response->body(), true);

        if (!is_array($apiResponse) || empty($apiResponse)) {
            throw new \Exception('Failed to retrieve user profiles. API response is invalid.');
        }

        return $apiResponse;
    }

    /**
     * Validate the user profile structure and type.
     *
     * @throws Exception
     */
    private function validateUserProfile(array $userProfile, string $expectedType): array
    {
        if (!isset($userProfile['id'])) {
            throw new \Exception('User ID is missing in the API response.');
        }

        if (!isset($userProfile['usertype'])) {
            throw new \Exception('User type is missing in the API response.');
        }

        if ($userProfile['usertype'] !== $expectedType) {
            throw new \Exception("User is not authorized to perform this action. Expected type: $expectedType");
        }

        return $userProfile;
    }

    /**
     * Get the sponsor user profile.
     *
     * @throws Exception
     */
    public function getAuthSponsorProfile($token): array
    {
        $apiResponse = $this->fetchProfiles($token);
        $userProfile = $apiResponse[0] ?? null;

        if (!$userProfile) {
            throw new \Exception('No user profiles found.');
        }

        $validatedProfile = $this->validateUserProfile($userProfile, 'sponsor');
        $this->upsertUser($validatedProfile);

        return $validatedProfile;
    }

    /**
     * Get the merchant user profile.
     *
     * @throws Exception
     */
    public function getAuthMerchantProfile($token): array
    {
        $apiResponse = $this->fetchProfiles($token);
        $userProfile = $apiResponse[0] ?? null;

        if (!$userProfile) {
            throw new \Exception('No user profiles found.');
        }

        $validatedProfile = $this->validateUserProfile($userProfile, 'merchant');
        $this->upsertUser($validatedProfile);

        return $validatedProfile;
    }

    /**
     * Get the generic user profile.
     *
     * @throws Exception
     */
    public function getAuthUserProfile($token): array
    {
        $apiResponse = $this->fetchProfiles($token);
        $userProfile = $apiResponse[0] ?? null;

        if (!$userProfile) {
            throw new \Exception('No user profiles found.');
        }

        $validatedProfile = $this->validateUserProfile($userProfile, 'user');
        $this->upsertUser($validatedProfile);

        return $validatedProfile;
    }

    /**
     * Insert or update the user in the `users` table and respective `merchant` or `sponsor` table.
     */
     
    private function upsertUser(array $userProfile): void
    {
        DB::transaction(function () use ($userProfile) {
            // Upsert into users table
            $userData = [
                'id' => $userProfile['id'],
                'email' => $userProfile['email'],
                'otp' => $userProfile['otp'],
                'phone' => $userProfile['phone'],
                'first_name' => $userProfile['first_name'],
                'middle_name' => $userProfile['middle_name'] ?? null,
                'last_name' => $userProfile['last_name'],
                'address' => $userProfile['address'],
                'state' => $userProfile['state'],
                'country' => $userProfile['country'],
                'city' => $userProfile['city'] ?? null,
                'image' => $userProfile['image'],
                'usertype' => $userProfile['usertype'],
                'nimc' => $userProfile['nimc'] ?? null,
                'bvn' => $userProfile['bvn'] ?? null,
                'age' => $userProfile['age'] ?? null,
                'gender' => $userProfile['gender'] ?? null,
                'isVerified' => isset($userProfile['email_verified_at']) ? 1 : 0,
                'remember_token' => $userProfile['remember_token'] ?? null,
                'created_at' => Carbon::parse($userProfile['created_at'])->format('Y-m-d H:i:s'),
                'updated_at' => Carbon::parse($userProfile['updated_at'])->format('Y-m-d H:i:s'),
            ];

            DB::table('users')->updateOrInsert(
                ['id' => $userProfile['id']],
                $userData
            );

            // Upsert into merchant or sponsor table
            $this->upsertUsertypeTable($userProfile);
        });
    }

    /**
     * Handle insertion or update into merchant or sponsor tables based on usertype.
     */
    private function upsertUsertypeTable(array $userProfile): void
    {
        $usertype = $userProfile['usertype'];
        $timestampData = [
            'created_at' => Carbon::parse($userProfile['created_at'])->format('Y-m-d H:i:s'),
            'updated_at' => Carbon::parse($userProfile['updated_at'])->format('Y-m-d H:i:s'),
        ];

        if ($usertype === 'merchant') {
            $merchantData = array_merge([
                'user_id' => $userProfile['id'],
                'deleted_at' => $userProfile['deleted_at'] ?? null,
            ], $timestampData);

            DB::table('merchant')->updateOrInsert(
                ['user_id' => $userProfile['id']],
                $merchantData
            );
        } elseif ($usertype === 'sponsor') {
            $sponsorData = array_merge([
                'user_id' => $userProfile['id'],
                'deleted_at' => $userProfile['deleted_at'] ?? null,
            ], $timestampData);

            DB::table('sponsor')->updateOrInsert(
                ['user_id' => $userProfile['id']],
                $sponsorData
            );
        }
    }
}
