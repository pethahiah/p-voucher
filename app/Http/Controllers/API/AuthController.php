<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Beneficiary;
use App\Models\User;
use App\Models\Merchant;
use App\Models\Sponsor;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use App\Http\Responses\ApiResponse;

class AuthController extends Controller
{

    /**
     * @group Authentication
     *
     * Register a new user.
     *
     * This endpoint registers a new user, creates associated records based on the user type, and optionally uploads a profile image.
     *
     * @bodyParam email string required The user's email address. Example: user@example.com
     * @bodyParam phone string required The user's phone number. Example: +1234567890
     * @bodyParam city string required The city where the user resides. Example: Lagos
     * @bodyParam address string required The user's address. Example: 123 Main St
     * @bodyParam state string required The state where the user resides. Example: Lagos
     * @bodyParam country string required The country where the user resides. Example: Nigeria
     * @bodyParam usertype string required The type of user: merchant or sponsor. Example: merchant
     * @bodyParam password string required The user's password. Example: Password123!
     * @bodyParam password_confirmation string required Confirmation of the user's password. Example: Password123!
     * @bodyParam image file optional An optional profile image. The file must be an image (jpg, jpeg, png, gif) and under 2MB.
     * @bodyParam store_name string optional The store name (required if usertype is merchant). Example: Super Store
     * @bodyParam store_description string optional A description of the store (required if usertype is merchant). Example: Best store in town
     * @bodyParam sponsor_name string optional The sponsor's name (required if usertype is sponsor). Example: Big Sponsor Inc
     * @bodyParam sponsor_registration_number string optional The sponsor's registration number (required if usertype is sponsor). Example: REG123456
     * @bodyParam sponsor_description string optional A description of the sponsor (required if usertype is sponsor). Example: Leading sponsor in the industry
     * @bodyParam type string optional The type of sponsor (required if usertype is sponsor). Example: Gold
     *
     * @response 201 {
     *     "success": true,
     *     "message": "User created successfully.",
     *     "data": {
     *         "id": 1,
     *         "email": "user@example.com",
     *         "phone": "+1234567890",
     *         "city": "Lagos",
     *         "address": "123 Main St",
     *         "state": "Lagos",
     *         "country": "Nigeria",
     *         "usertype": "merchant",
     *         "image": "http://localhost/storage/images/image.jpg",
     *         "created_at": "2024-01-01T00:00:00.000000Z",
     *         "updated_at": "2024-01-01T00:00:00.000000Z"
     *     }
     * }
     *
     * @response 422 {
     *     "success": false,
     *     "message": "Validation error",
     *     "errors": {
     *         "email": ["The email has already been taken."],
     *         "password": ["The password confirmation does not match."]
     *     }
     * }
     */
    public function register(Request $request): JsonResponse
    {
        // Validate the request
        $validator = Validator::make($request->all(), [
            'email' => 'required|email|unique:users',
            'phone' => 'required|string|unique:users',
            'city' => 'required|string',
            'address' => 'required|string',
            'state' => 'required|string',
            'country' => 'required|string',
            'usertype' => 'required|string|in:merchant,sponsor',
            'password' => [
                'required',
                'confirmed',
                'min:8',
                'regex:/^(?=.*?[A-Z])(?=.*?[a-z])(?=.*?[0-9])(?=.*?[#?!@$%^&*-]).{8,}$/'
            ],
            'password_confirmation' => 'required|same:password',
            'image' => 'nullable|image|mimes:jpg,jpeg,png,gif|max:2048',
        ]);

        if ($validator->fails()) {
            return ApiResponse::error('Validation error', $validator->errors()->toArray(), 422);
        }

        // Handle image upload if exists
        $imagePath = null;
        if ($request->hasFile('image')) {
            $image = $request->file('image');
            $imagePath = $image->store('images', 'public');
            // Generate the full URL path
            $imagePath = asset('storage/' . $imagePath);
        }

        // Prepare user data
        $userData = $request->only(['email', 'phone', 'city', 'address', 'state', 'country', 'usertype']);
        $userData['password'] = Hash::make($request->input('password'));
        $userData['image'] = $imagePath;

        // Create the user
        $user = User::create($userData);
        Log::info('User ID: ' . $user->id);
        // Create the beneficiary record
        Beneficiary::create([
            'user_id' => $user->id,
            'state' => $request->input('state'),
        ]);

        // Create related records based on user type
        $usertype = $request->input('usertype');
        if ($usertype === 'merchant') {
            Merchant::create([
                'user_id' => $user->id,
                'store_name' => $request->input('store_name'),
                'store_description' => $request->input('store_description'),
            ]);
        } elseif ($usertype === 'sponsor') {
            Sponsor::create([
                'user_id' => $user->id,
                'sponsor_name' => $request->input('sponsor_name'),
                'sponsor_registration_number' => $request->input('sponsor_registration_number'),
                'sponsor_description' => $request->input('sponsor_description'),
                'type' => $request->input('type'),
            ]);
        }

        return ApiResponse::success('User created successfully.', $user, 201);
    }


    /**
     * @group Authentication
     *
     * Attempt to login and send an OTP.
     *
     * This endpoint verifies the user's email and sends an OTP to the user's email for authentication.
     *
     * @bodyParam email string required The user's email address. Example: user@example.com
     * @bodyParam password string required The user's password. Example: Password123!
     *
     * @response 200 {
     *     "success": true,
     *     "message": "OTP sent successfully.",
     *     "data": null
     * }
     *
     * @response 401 {
     *     "success": false,
     *     "message": "Invalid credentials.",
     *     "error": "Detailed error message"
     * }
     *
     * @response 422 {
     *     "success": false,
     *     "message": "Validation error",
     *     "errors": {
     *         "email": ["The email field is required."],
     *         "password": ["The password field is required."]
     *     }
     * }
     */


    public function AttemptLogin(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'email' => 'required|email',
            'password' => 'required|string',
        ]);

        if ($validator->fails()) {
            return ApiResponse::error('Validation error', $validator->errors()->toArray(), 422);
        }

        $email = $request->input('email');
        $password = $request->input('password');
        $user = User::where('email', $email)->first();

        if ($user && Hash::check($password, $user->password)) {
            $otp = str_pad(random_int(0, 999999), 6, '0', STR_PAD_LEFT);
            Log::info("OTP = " . $otp);

            // Update user's OTP in the database
            $user->otp = $otp;
            $user->save();

            // Send email with OTP
            $data = [
                'otp' => $otp,
                'email' => $email,
            ];
            $subject = 'e-voucher: ONE TIME PASSWORD';
            Mail::send('Email.otp', $data, function ($message) use ($email, $subject) {
                $message->to($email)->subject($subject);
            });
            return ApiResponse::success('OTP sent successfully.',200);

        } else {
            return ApiResponse::error('Invalid credentials.', [], 401);
        }
    }


    /**
     * @group Authentication
     *
     * Login using OTP.
     *
     * This endpoint logs the user in using the OTP sent to their email.
     *
     * @bodyParam email string required The user's email address. Example: user@example.com
     * @bodyParam otp string required The OTP sent to the user's email. Example: 123456
     *
     * @response 200 {
     *     "success": true,
     *     "message": "Login successful.",
     *     "data": "Bearer token"
     * }
     *
     * @response 401 {
     *     "success": false,
     *     "message": "Invalid OTP or email.",
     *     "error": "Detailed error message"
     * }
     *
     * @response 422 {
     *     "success": false,
     *     "message": "Validation error",
     *     "errors": {
     *         "email": ["The email field is required."],
     *         "otp": ["The otp field is required."]
     *     }
     * }
     */

    public function loginViaOtp(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'email' => 'required|email',
            'otp' => 'required|numeric|digits:6',
        ]);

        if ($validator->fails()) {
            return ApiResponse::error('Validation error', $validator->errors()->toArray(), 422);
        }

        $user = User::where([
            ['email', $request->input('email')],
            ['otp', $request->input('otp')]
        ])->first();

        if ($user) {
            Auth::login($user, true);
            $user->otp = null;
            $user->save();
            $token = $user->createToken('MyAuthApp')->plainTextToken;
            return ApiResponse::success('Login successful.', $token, 200);
        } else {
            return ApiResponse::error('Invalid OTP or email.', [], 401);
        }
    }

    /**
     * @group Authentication
     *
     * Logout the currently authenticated user.
     *
     * This endpoint logs out the currently authenticated user and revokes all their tokens.
     *
     * @response 200 {
     *     "success": true,
     *     "message": "Success! You are logged out.",
     *     "data": null
     * }
     *
     * @response 403 {
     *     "success": false,
     *     "message": "Failed! You are already logged out.",
     *     "error": "Detailed error message"
     * }
     */

    public function logout(): JsonResponse
    {
        if (Auth::check()) {
            // Revoke all tokens for the currently authenticated user
            Auth::user()->tokens()->delete();
            Auth::logout(); // Ensure the user is logged out
            return ApiResponse::success('Success! You are logged out.', 200);
        }
        return ApiResponse::error('Failed! You are already logged out.', 403);
    }


}
