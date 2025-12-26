<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\UserProfile;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    // REGISTER USER
    public function register(Request $request)
    {
        $request->validate([
            'national_id' => 'required|string|unique:users,national_id_hash',
            'email' => 'required|email|unique:users,email_hash',
            'password' => 'required|string|min:6',
            'first_name' => 'required|string',
            'last_name' => 'required|string',
        ]);

        // Create user
        $user = User::create([
            'global_user_uuid' => Str::uuid(),
            'national_id_hash' => hash('sha256', $request->national_id),
            'national_id_encrypted' => Crypt::encryptString($request->national_id),
            'national_id_country_code' => $request->national_id_country_code ?? 'UG',
            'data_residency_region' => $request->data_residency_region ?? 'AF',
            'email_encrypted' => Crypt::encryptString($request->email),
            'email_hash' => hash('sha256', $request->email),
            'password_hash' => Hash::make($request->password),
        ]);
            Log::info($user);
        // Create profile
        $userProfile=UserProfile::create([
            'user_id' => $user->id,
            'first_name' => $request->first_name,
            'last_name' => $request->last_name,
            'display_name' => $request->display_name ?? $request->first_name . ' ' . $request->last_name,
            'dob' => $request->dob,
            'gender' => $request->gender,
            'address_line1' => $request->address_line1,
            'address_line2' => $request->address_line2,
            'city' => $request->city,
            'state' => $request->state,
            'country' => $request->country,
            'postal_code' => $request->postal_code,
        ]);

        return response()->json([
            'message' => 'User registered successfully',
        ], 201);
        Log::info($userProfile);
    }

    // LOGIN USER
        public function login(Request $request)
        {
            $request->validate([
                'email' => 'required|email',
                'password' => 'required|string',
            ]);

            $emailHash = hash('sha256', strtolower(trim($request->email)));

            $user = User::where('email_hash', $emailHash)->first();

            Log::info('Login attempt', [
                'email_hash' => $emailHash,
                'user_found' => (bool) $user,
                'ip' => $request->ip(),
            ]);

            if (!$user || !Hash::check($request->password, $user->password_hash)) {
                throw ValidationException::withMessages([
                    'email' => ['Invalid credentials'],
                ]);
            }

            // Update login tracking
            $user->update([
                'last_login_at' => now(),
                'last_login_ip' => $request->ip(),
                'last_login_user_agent' => $request->userAgent(),
                'failed_login_attempts' => 0,
            ]);

            $token = $user->createToken('api-token')->plainTextToken;

            return response()->json([
                'access_token' => $token,
                'token_type' => 'Bearer',
            ]);
        }

}

