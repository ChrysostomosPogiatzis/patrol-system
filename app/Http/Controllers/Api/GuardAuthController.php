<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Guard;
use App\Models\GuardOtpToken;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\RateLimiter;

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

        // Check company subscription
        if ($guard->tenant) {
            $isExpired = $guard->tenant->subscription_until && $guard->tenant->subscription_until->isPast();
            $isInactive = !$guard->tenant->is_active;

            if ($isInactive || $isExpired) {
                return response()->json([
                    'message' => 'Your company subscription has expired or is inactive. Access restricted.',
                ], 403);
            }
        }

        // Enforce rate limiting: Max 2 OTP requests per 1 hour
        $cleanPhone = preg_replace('/[^0-9]/', '', $request->phone);
        $rateLimitKey = 'otp-request:' . $cleanPhone;

        if (RateLimiter::tooManyAttempts($rateLimitKey, 2)) {
            $seconds = RateLimiter::availableIn($rateLimitKey);
            $minutes = ceil($seconds / 60);
            return response()->json([
                'message' => "Too many OTP requests. Please wait {$minutes} minutes before trying again.",
            ], 429);
        }

        RateLimiter::hit($rateLimitKey, 3600); // 1 hour lockout window

        // Generate a cryptographically secure 6-digit OTP
        $otp = (string) random_int(100000, 999999);

        // Store hashed OTP token in the DB
        GuardOtpToken::create([
            'guard_id' => $guard->id,
            'token_hash' => bcrypt($otp),
            'expires_at' => now()->addMinutes(5),
            'used' => false,
        ]);

        // Clean phone number format for SMS sending
        $cleanPhoneSms = preg_replace('/[^0-9+]/', '', $request->phone);

        // Prepare message body
        $message = "Your SENTINEL PATROL verification code is: {$otp}. Valid for 5 minutes.";

        // Dispatch real SMS
        $smsSent = $this->sendSms($cleanPhoneSms, $message);

        // Log OTP locally for backend diagnostics
        Log::info("OTP generated for guard ID {$guard->id}: {$otp} (SMS Sent: " . ($smsSent ? 'YES' : 'NO') . ")");

        $responseData = [
            'message' => $smsSent ? 'OTP sent successfully via SMS.' : 'OTP generated (SMS dispatch failed, check log).',
            'phone' => $request->phone,
        ];

        // Return OTP in response only for local testing / debug environments
        if (config('app.debug') || app()->environment('local')) {
            $responseData['otp'] = $otp;
        }

        return response()->json($responseData);
    }

    /**
     * Helper to send an SMS via Routee Gateway.
     */
    private function sendSms(string $to, string $message): bool
    {
        $appId = config('services.routee.app_id');
        $appSecret = config('services.routee.app_secret');
        $from = config('services.routee.from', 'PaphosEvent');

        if (!$appId || !$appSecret) {
            Log::warning('Routee SMS app credentials are not set in config.');
            return false;
        }

        try {
            // Step 1: Obtain OAuth Access Token from Routee
            $tokenResponse = Http::asForm()
                ->withBasicAuth($appId, $appSecret)
                ->timeout(5)
                ->post('https://auth.routee.net/oauth/token', [
                    'grant_type' => 'client_credentials'
                ]);

            if (!$tokenResponse->successful()) {
                Log::warning('Routee SMS - Token request failed', [
                    'status' => $tokenResponse->status(),
                    'body' => $tokenResponse->body()
                ]);
                return false;
            }

            $accessToken = $tokenResponse->json()['access_token'] ?? null;
            if (!$accessToken) {
                Log::warning('Routee SMS - No access token returned');
                return false;
            }

            // Step 2: Post SMS to Routee connect endpoint
            $smsResponse = Http::withToken($accessToken)
                ->acceptJson()
                ->timeout(5)
                ->post('https://connect.routee.net/sms', [
                    'body' => $message,
                    'to' => $to,
                    'from' => $from,
                ]);

            if ($smsResponse->successful()) {
                Log::info("Routee SMS successfully sent to {$to}");
                return true;
            }

            Log::warning('Routee SMS - Send request failed', [
                'status' => $smsResponse->status(),
                'response' => $smsResponse->body()
            ]);
            return false;

        } catch (\Exception $e) {
            Log::error('Routee SMS - Exception during dispatch', [
                'error' => $e->getMessage()
            ]);
            return false;
        }
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

        // Check company subscription
        if ($guard->tenant) {
            $isExpired = $guard->tenant->subscription_until && $guard->tenant->subscription_until->isPast();
            $isInactive = !$guard->tenant->is_active;

            if ($isInactive || $isExpired) {
                return response()->json([
                    'message' => 'Your company subscription has expired or is inactive. Access restricted.',
                ], 403);
            }
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
