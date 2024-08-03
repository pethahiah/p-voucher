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
