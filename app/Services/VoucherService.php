<?php


namespace App\Services;

use App\Models\Transaction;
use App\Models\Voucher;
use Carbon\Carbon;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\DB;
use App\Models\Beneficiary;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;

class VoucherService
{
    public function createVoucher(array $data)
    {
        // Generate a unique voucher code
        $data['voucher_code'] = Str::upper(Str::random(10));

        if (!isset($data['sponsor_id'])) {
            throw new \Exception('Missing sponsor_id.');
        }

        // Create the voucher
        $voucher = Voucher::create($data);

        // If merchant_ids are provided, associate them with the voucher
        if (isset($data['merchant_id']) && is_array($data['merchant_id'])) {
            // Prepare pivot data with voucher_code
            $pivotData = array_fill_keys($data['merchant_id'], ['voucher_code' => $data['voucher_code']]);
            // Sync merchants with the voucher
            $voucher->merchants()->sync($pivotData);
        }

        // Reload the voucher with merchants and the pivot data
        $voucher->load('merchants');

        return $voucher;
    }

    public function updateVoucher(int $id, array $data): Voucher
    {
        try {
            // Find the voucher by ID
            $voucher = Voucher::findOrFail($id);

            // Update the voucher's attributes
            $voucher->update($data);

            // If merchant_ids are provided, update the associated merchants
            if (isset($data['merchant_id']) && is_array($data['merchant_id'])) {
                // Prepare pivot data if necessary, e.g., for timestamps or additional fields
                $pivotData = array_fill_keys($data['merchant_id'], ['voucher_code' => $voucher->voucher_code]);

                // Sync merchants with the voucher, keeping pivot data
                $voucher->merchants()->sync($pivotData);
            }

            // Reload the voucher with merchants and pivot data
            $voucher->load('merchants');

            return $voucher;
        } catch (ModelNotFoundException $e) {
            throw new ModelNotFoundException("Voucher not found.");
        } catch (QueryException $e) {
            throw new QueryException("Failed to update voucher: " . $e->getMessage(), $e->errorInfo, $e);
        }
    }


    /**
     * Soft delete a voucher.
     *
     * @param int $id
     * @return bool
     * @throws ModelNotFoundException
     */
    public function revokeVoucher(int $id): bool
    {
        try {
            $voucher = Voucher::findOrFail($id);
            return $voucher->delete();
        } catch (ModelNotFoundException $e) {
            throw new ModelNotFoundException("Voucher not found.");
        } catch (QueryException $e) {
            throw new QueryException("Failed to revoke voucher: " . $e->getMessage(), $e->errorInfo, $e);
        }
    }

    /**
     * Permanently delete a voucher.
     *
     * @param int $id
     * @return bool
     * @throws ModelNotFoundException
     */
    public function deleteVoucher(int $id): bool
    {
        try {
            $voucher = Voucher::findOrFail($id);

            // Detach associated merchants if necessary
            $voucher->merchants()->detach();

            return $voucher->forceDelete();
        } catch (ModelNotFoundException $e) {
            throw new ModelNotFoundException("Voucher not found.");
        } catch (QueryException $e) {
            throw new QueryException("Failed to delete voucher: " . $e->getMessage(), $e->errorInfo, $e);
        }
    }

    /**
     * Redeem a voucher and validate location.
     *
     * @param string $voucherCode
     * @param string $ipAddress
     * @return array
     */
    public function redeemVoucher($voucherCode, $ipAddress): array
    {
        Log::info("Attempting to redeem voucher", [
            'voucher_code' => $voucherCode,
            'ip_address' => $ipAddress,
        ]);

        // Find the voucher by code
        $voucher = Voucher::where('voucher_code', $voucherCode)->first();

        if (!$voucher) {
            Log::warning("Voucher not found", ['voucher_code' => $voucherCode]);
            return ['success' => false, 'message' => 'Voucher not found.'];
        }

        // Check if the voucher has expired
        if ($voucher->expiry_date < now()) {
            Log::warning("Voucher expired", ['voucher_code' => $voucherCode, 'expiry_date' => $voucher->expiry_date]);
            return ['success' => false, 'message' => 'Voucher has expired.'];
        }

        Log::info("Voucher expiry date valid", ['voucher_code' => $voucherCode]);

        // Check if the voucher has already been used (one-time type)
        if ($voucher->type === 'one_time' && $voucher->voucher_status === 'used') {
            Log::warning("Voucher already used", ['voucher_code' => $voucherCode]);
            return ['success' => false, 'message' => 'Voucher has already been used.'];
        }

        // Validate the beneficiary's location
        $beneficiaryLocation = $this->getLocationByIp($ipAddress);
        Log::info("Beneficiary location determined", ['ip_address' => $ipAddress, 'location' => $beneficiaryLocation]);

        if ($voucher->location !== $beneficiaryLocation) {
            Log::warning("Voucher location mismatch", [
                'voucher_code' => $voucherCode,
                'voucher_location' => $voucher->location,
                'beneficiary_location' => $beneficiaryLocation
            ]);
            return ['success' => false, 'message' => 'Voucher is not valid in your location.'];
        }

        Log::info("Voucher location valid", ['voucher_code' => $voucherCode]);

        // Begin database transaction
        DB::beginTransaction();

        try {
            // Reduce the voucher limit and update status if necessary
            if ($voucher->limit > 0) {
                $voucher->limit -= 1;
                if ($voucher->limit === 0 && $voucher->type === 'one_time') {
                    $voucher->voucher_status = 'used';
                }
                $voucher->save();
                Log::info("Voucher limit updated", ['voucher_code' => $voucherCode, 'new_limit' => $voucher->limit]);
            } else {
                Log::warning("Voucher limit reached zero", ['voucher_code' => $voucherCode]);
                DB::rollBack();
                return ['success' => false, 'message' => 'No more vouchers available.'];
            }

            // Create a transaction record
            $transaction = new Transaction();
            $transaction->voucher_id = $voucher->id;
            $transaction->beneficiary_id = Auth::id();
            $transaction->merchant_id = $voucher->merchant_id;
            $transaction->amount = $voucher->voucher_amount;
            $transaction->status = 'used';
            $transaction->code = $voucherCode;
            $transaction->type = $voucher->type;
            $transaction->code_generation_method = $voucher->code_generation_method;
            $transaction->save();

            Log::info("Transaction created", ['transaction' => $transaction->toArray()]);

            DB::commit();
            Log::info("Voucher redeemed successfully", ['voucher_code' => $voucherCode]);
            return ['success' => true, 'message' => 'Voucher redeemed successfully.'];

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error("Error redeeming voucher", ['voucher_code' => $voucherCode, 'error' => $e->getMessage()]);
            return ['success' => false, 'message' => 'Failed to redeem voucher.'];
        }
    }

    /**
     * Get location based on IP address using a geolocation service.
     *
     * @param string $ipAddress
     * @return string
     */
    private function getLocationByIp(string $ipAddress): string
    {
        $response = Http::get("http://ipinfo.io/{$ipAddress}/json");

        if ($response->successful()) {
            $data = $response->json();
            return $data['city'] ?? 'Unknown';
        }

        return 'Unknown';
    }


    /**
     * Fetch all vouchers created by a specific sponsor with pagination.
     *
     * @param int $sponsorId
     * @param int $perPage
     * @return LengthAwarePaginator
     */
    public function getVouchersBySponsor(int $sponsorId, int $perPage = 15): LengthAwarePaginator
    {
        return Voucher::where('sponsor_id', $sponsorId)->paginate($perPage);
    }


    /**
     * Fetch vouchers by date range.
     *
     * @param string $startDate
     * @param string $endDate
     * @return Collection
     */
    public function getVouchersByDateRange($sponsorId, string $startDate, string $endDate, int $perPage = 15): Collection
    {
        try {
            // Validate date format
            $validatedStartDate = Carbon::parse($startDate)->startOfDay();
            $validatedEndDate = Carbon::parse($endDate)->endOfDay();

            // Query vouchers within the date range
            $vouchers = Voucher::whereBetween('created_at', [$validatedStartDate, $validatedEndDate])
                ->where('sponsor_id', $sponsorId)
                ->paginate($perPage);

            return $vouchers;
        } catch (\Exception $e) {
            Log::error('Failed to fetch vouchers by date range', ['error' => $e->getMessage()]);
            throw new \Exception('Error fetching vouchers by date range.');
        }
    }


    /**
     * Fetch all used vouchers created by a specific sponsor with pagination.
     *
     * @param int $sponsorId
     * @param int $perPage
     * @return LengthAwarePaginator
     */
    public function getUsedVouchers(int $sponsorId, int $perPage = 15): LengthAwarePaginator
    {
        return Voucher::where('sponsor_id', $sponsorId)
            ->where('voucher_status', 'used')
            ->paginate($perPage);
    }

    /**
     * Fetch all redeemed vouchers created by a specific sponsor with pagination.
     *
     * @param int $sponsorId
     * @param int $perPage
     * @return LengthAwarePaginator
     */
    public function getRedeemedVouchers(int $sponsorId, int $perPage = 15): LengthAwarePaginator
    {
        return Voucher::where('sponsor_id', $sponsorId)
            ->whereHas('merchants', function ($query) {
                $query->whereNotNull('voucher_code');
            })
            ->paginate($perPage);
    }

    /**
     * Fetch all beneficiaries who redeemed vouchers created by a specific sponsor with pagination.
     *
     * @param int $sponsorId
     * @param int $perPage
     * @return LengthAwarePaginator
     */
    public function getBeneficiariesWithRedeemedVouchers(int $sponsorId, int $perPage = 15): LengthAwarePaginator
    {
        return Beneficiary::whereHas('vouchers', function ($query) use ($sponsorId) {
            $query->where('sponsor_id', $sponsorId)
                ->whereNotNull('voucher_code');
        })->paginate($perPage);
    }

    /**
     * Fetch all vouchers created by a specific sponsor that are yet to be redeemed with pagination.
     *
     * @param int $sponsorId
     * @param int $perPage
     * @return LengthAwarePaginator
     */
    public function getVouchersYetToBeRedeemed(int $sponsorId, int $perPage = 15): LengthAwarePaginator
    {
        return Voucher::where('sponsor_id', $sponsorId)
            ->where('voucher_status', 'unused')
            ->paginate($perPage);
    }

}
