<?php

use App\Http\Controllers\API\AuthController;
use App\Http\Controllers\API\MerchantController;
use App\Http\Controllers\API\SponsorController;
use App\Http\Controllers\API\VoucherController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| is assigned the "api" middleware group. Enjoy building your API!
|
*/


//Route::namespace('API')->group(function (){
    Route::post('/attemptLogin', [AuthController::class, 'AttemptLogin']);
    Route::post('/loginViaOtp', [AuthController::class, 'loginViaOtp']);
    Route::post('/register', [AuthController::class, 'register']);

Route::middleware(['auth:sanctum'])->group(function () {
        Route::get('/logout', [AuthController::class, 'logout']);
        Route::post('vouchers', [VoucherController::class, 'store']);
        Route::post('vouchers/redeem', [VoucherController::class, 'redeem']);
        Route::post('update/{VoucherId}/voucher', [VoucherController::class, 'update']);
        Route::post('revoke/{VoucherId}', [VoucherController::class, 'revoke']);
        Route::post('delete-voucher/{VoucherId}', [VoucherController::class, 'destroy']);
    // Route to fetch all vouchers created by a sponsor
        Route::get('/vouchers/by-sponsor', [VoucherController::class, 'getVouchersBySponsor']);
    // Route to fetch all used vouchers
        Route::get('/vouchers/used', [VoucherController::class, 'getUsedVouchers']);
    // Route to fetch all redeemed vouchers
        Route::get('/vouchers/redeemed', [VoucherController::class, 'getRedeemedVouchers']);
    // Route to fetch all beneficiaries who redeemed vouchers
        Route::get('/beneficiaries/redeemed', [VoucherController::class, 'getBeneficiariesWithRedeemedVouchers']);
    // Route to fetch all vouchers yet to be redeemed
        Route::get('/vouchers/yet-to-be-redeemed', [VoucherController::class, 'getVouchersYetToBeRedeemed']);

        Route::get('/merchants/{id}', [MerchantController::class, 'getMerchantProfile']);
        Route::get('/sponsors/{id}', [SponsorController::class, 'getSponsorDetails']);
        Route::get('/voucher-search/date-range', [VoucherController::class, 'getVouchersByDateRange']);

});


