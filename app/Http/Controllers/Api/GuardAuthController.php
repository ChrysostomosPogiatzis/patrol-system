<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Guard;
use App\Models\GuardOtpToken;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;

class GuardAuthController extends Controller
{
    /**
     * Generate and send a 6-digit OTP to the guard's phone.
     */
    public function sendOtp(Request $request): JsonResponse
    {
        $request->validate([
            'phone' => 'required|string',
        ]);

        $guard = Guard::where('phone', $request->phone)
            ->where('is_active', true)
            ->first();

        if (!$guard) {
            return response()->json([
                'message' => 'Guard account not found or inactive.',
            ], 404);
        }

        // Generate a cryptographically secure 6-digit OTP
        $otp = (string) random_int(100000, 999999);

        // Store hashed OTP token in the DB
        GuardOtpToken::create([
            'guard_id' => $guard->id,
            'token_hash' => bcrypt($otp),
            'expires_at' => now()->addMinutes(5),
            'used' => false,
        ]);

        // Log OTP locally for backend diagnostics/SMS service simulation
        Log::info("OTP generated for guard ID {$guard->id}: {$otp}");

        // Return OTP in response for development and API testing
        return response()->json([
            'message' => 'OTP sent successfully (Simulated).',
            'phone' => $request->phone,
            'otp' => $otp, // Return for testing/development
        ]);
    }

    /**
     * Authenticate guard using phone number and OTP.
     */
    public function login(Request $request): JsonResponse
    {
        $request->validate([
            'phone' => 'required|string',
            'otp' => 'required|string|size:6',
        ]);

        $guard = Guard::where('phone', $request->phone)
            ->where('is_active', true)
            ->first();

        if (!$guard) {
            return response()->json([
                'message' => 'Guard account not found or inactive.',
            ], 404);
        }

        // Find the latest active OTP token
        $otpToken = GuardOtpToken::where('guard_id', $guard->id)
            ->where('used', false)
            ->where('expires_at', '>', now())
            ->orderBy('id', 'desc')
            ->first();

        if (!$otpToken || !Hash::check($request->otp, $otpToken->token_hash)) {
            return response()->json([
                'message' => 'Invalid or expired OTP.',
            ], 422);
        }

        // Mark OTP token as used
        $otpToken->update(['used' => true]);

        // Generate Sanctum token for guard
        $token = $guard->createToken('guard-mobile-app', ['guard'])->plainTextToken;

        // Update last login timestamp
        $guard->update(['last_login_at' => now()]);

        return response()->json([
            'message' => 'Login successful.',
            'token' => $token,
            'guard' => [
                'id' => $guard->id,
                'tenant_id' => $guard->tenant_id,
                'full_name' => $guard->full_name,
                'phone' => $guard->phone,
                'employee_id' => $guard->employee_id,
                'avatar_url' => $guard->avatar_url,
            ],
        ]);
    }

    /**
     * Get authenticated guard profile.
     */
    public function me(Request $request): JsonResponse
    {
        return response()->json([
            'guard' => $request->user(),
        ]);
    }
}
