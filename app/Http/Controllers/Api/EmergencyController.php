<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\GuardLocationPing;
use App\Models\NotificationLog;
use App\Models\Patrol;
use App\Models\PatrolContact;
use App\Models\SosAlert;
use App\Models\SosLocationPing;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class EmergencyController extends Controller
{
    /**
     * Submit a background location ping.
     */
    public function pingLocation(Request $request): JsonResponse
    {
        $request->validate([
            'latitude' => 'required|numeric|between:-90,90',
            'longitude' => 'required|numeric|between:-180,180',
            'accuracy_m' => 'nullable|numeric|min:0',
            'battery_pct' => 'nullable|integer|between:0,100',
            'is_online' => 'nullable|boolean',
            'patrol_id' => 'nullable|exists:patrols,id',
        ]);

        $guard = $request->user();

        // Log background GPS location ping
        GuardLocationPing::create([
            'tenant_id' => $guard->tenant_id,
            'guard_id' => $guard->id,
            'patrol_id' => $request->patrol_id,
            'latitude' => $request->latitude,
            'longitude' => $request->longitude,
            'accuracy_m' => $request->accuracy_m,
            'battery_pct' => $request->battery_pct,
            'is_online' => $request->is_online ?? true,
            'recorded_offline' => false,
            'pinged_at' => now(),
        ]);

        // Update last seen metadata on guard account
        $guard->update([
            'last_seen_at' => now(),
        ]);

        return response()->json([
            'message' => 'Location ping recorded successfully.',
        ]);
    }

    /**
     * Trigger an emergency SOS alert.
     */
    public function triggerSos(Request $request): JsonResponse
    {
        $request->validate([
            'latitude' => 'required|numeric|between:-90,90',
            'longitude' => 'required|numeric|between:-180,180',
            'note' => 'nullable|string',
        ]);

        $guard = $request->user();

        // Detect if guard has an active patrol session
        $patrol = Patrol::where('guard_id', $guard->id)
            ->where('status', 'in_progress')
            ->first();

        // Create the active SOS alert
        $sos = SosAlert::create([
            'tenant_id' => $guard->tenant_id,
            'guard_id' => $guard->id,
            'patrol_id' => $patrol ? $patrol->id : null,
            'status' => 'active',
            'triggered_latitude' => $request->latitude,
            'triggered_longitude' => $request->longitude,
            'note' => $request->note,
            'triggered_at' => now(),
        ]);

        // Identify patrol contacts to notify (SaaS Tenancy scope)
        $contacts = PatrolContact::where('is_active', true)->get();

        foreach ($contacts as $contact) {
            // Check if contact subscribes to sos_triggered
            $channels = $contact->notify_channels ?? ['email'];
            $subscribedEvents = $contact->notify_on ?? [];

            if (in_array('sos_triggered', $subscribedEvents)) {
                foreach ($channels as $channel) {
                    // Log notification dispatch in audit / logs
                    NotificationLog::create([
                        'tenant_id' => $guard->tenant_id,
                        'contact_id' => $contact->id,
                        'guard_id' => $guard->id,
                        'channel' => $channel,
                        'trigger' => 'sos_triggered',
                        'entity_type' => 'sos_alert',
                        'entity_id' => $sos->id,
                        'recipient' => $channel === 'email' ? $contact->email : $contact->phone,
                        'subject' => "ALERT: Emergency SOS Triggered by {$guard->full_name}",
                        'body' => "SOS Triggered at Coordinates ({$request->latitude}, {$request->longitude}). Note: " . ($request->note ?? 'None'),
                        'sent' => true,
                        'sent_at' => now(),
                    ]);

                    Log::warning("SOS Alert dispatched to contact {$contact->name} via {$channel}.");
                }
            }
        }

        return response()->json([
            'message' => 'Emergency SOS alert triggered successfully.',
            'sos_alert' => $sos,
        ]);
    }

    /**
     * Submit real-time coordinates for an active SOS session.
     */
    public function pingSosLocation(Request $request, $sosId): JsonResponse
    {
        $request->validate([
            'latitude' => 'required|numeric|between:-90,90',
            'longitude' => 'required|numeric|between:-180,180',
            'accuracy_m' => 'nullable|numeric|min:0',
        ]);

        $guard = $request->user();

        // Verify that the SOS alert belongs to this guard's tenant and is active
        $sos = SosAlert::where('id', $sosId)
            ->where('status', 'active')
            ->first();

        if (!$sos) {
            return response()->json([
                'message' => 'Active SOS session not found.',
            ], 404);
        }

        SosLocationPing::create([
            'sos_alert_id' => $sos->id,
            'guard_id' => $guard->id,
            'latitude' => $request->latitude,
            'longitude' => $request->longitude,
            'accuracy_m' => $request->accuracy_m,
            'pinged_at' => now(),
        ]);

        return response()->json([
            'message' => 'SOS location update logged.',
        ]);
    }
}
