<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Checkpoint;
use App\Models\CheckpointMedia;
use App\Models\Patrol;
use App\Models\PatrolCheckpointLog;
use App\Models\Route;
use App\Models\RouteAssignment;
use App\Models\RouteCheckpoint;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PatrolController extends Controller
{
    /**
     * List all routes assigned to the authenticated guard.
     */
    public function routes(Request $request): JsonResponse
    {
        $guard = $request->user();

        // Get active route assignments
        $routeIds = RouteAssignment::where('guard_id', $guard->id)
            ->where('is_active', true)
            ->pluck('route_id');

        $routes = Route::whereIn('id', $routeIds)
            ->where('is_active', true)
            ->with(['routeCheckpoints' => function ($query) {
                $query->orderBy('position');
            }, 'routeCheckpoints.checkpoint'])
            ->get();

        return response()->json([
            'routes' => $routes,
        ]);
    }

    /**
     * Return the guard's current in-progress patrol (if any), with checkpoint logs.
     * Used by the frontend on boot to auto-resume a stuck/active session.
     */
    public function activePatrol(Request $request): JsonResponse
    {
        $guard = $request->user();

        $patrol = Patrol::where('guard_id', $guard->id)
            ->where('status', 'in_progress')
            ->with(['checkpointLogs.checkpoint', 'route'])
            ->first();

        return response()->json([
            'patrol' => $patrol, // null if none
        ]);
    }

    /**
     * Return a paginated list of the guard's completed/abandoned patrols.
     */
    public function patrolHistory(Request $request): JsonResponse
    {
        $guard = $request->user();

        $patrols = Patrol::where('guard_id', $guard->id)
            ->whereIn('status', ['completed', 'abandoned'])
            ->with('route:id,name')
            ->orderByDesc('started_at')
            ->paginate(20);

        return response()->json($patrols);
    }

    /**
     * Return full detail of a single patrol — logs, notes, GPS, media, incidents.
     * Guards can only access their own patrols.
     */
    public function patrolDetail(Request $request, $patrolId): JsonResponse
    {
        $guard = $request->user();

        $patrol = Patrol::where('id', $patrolId)
            ->where('guard_id', $guard->id)
            ->with([
                'route:id,name,enforce_order,allow_skip',
                'checkpointLogs' => function ($q) {
                    $q->orderBy('position')
                      ->with(['checkpoint', 'media']);
                },
                'incidents:id,patrol_id,title,description,priority,status,created_at,incident_latitude,incident_longitude',
            ])
            ->firstOrFail();

        return response()->json(['patrol' => $patrol]);
    }


    /**
     * Start a new patrol session.
     */
    public function startPatrol(Request $request): JsonResponse
    {
        $request->validate([
            'route_id' => 'required|exists:routes,id',
        ]);

        $guard = $request->user();

        // Verify if route is active
        $route = Route::where('id', $request->route_id)
            ->where('is_active', true)
            ->first();

        if (!$route) {
            return response()->json([
                'message' => 'The selected route is inactive or unavailable.',
            ], 422);
        }

        // Check if there is an active patrol session for this guard
        $activePatrol = Patrol::where('guard_id', $guard->id)
            ->where('status', 'in_progress')
            ->first();

        if ($activePatrol) {
            return response()->json([
                'message' => 'You already have an active patrol session.',
                'patrol' => $activePatrol->load(['checkpointLogs.checkpoint', 'route']),
            ], 422);
        }

        // Get route checkpoints
        $routeCheckpoints = RouteCheckpoint::where('route_id', $route->id)
            ->orderBy('position')
            ->get();

        if ($routeCheckpoints->isEmpty()) {
            return response()->json([
                'message' => 'Cannot start a patrol on a route with no checkpoints.',
            ], 422);
        }

        // Create the patrol record
        $patrol = Patrol::create([
            'tenant_id' => $guard->tenant_id,
            'route_id' => $route->id,
            'guard_id' => $guard->id,
            'status' => 'in_progress',
            'started_at' => now(),
            'total_checkpoints' => $routeCheckpoints->count(),
            'completed_checkpoints' => 0,
            'skipped_checkpoints' => 0,
            'incident_count' => 0,
        ]);

        // Initialize log entries for each checkpoint in the sequence
        foreach ($routeCheckpoints as $rc) {
            PatrolCheckpointLog::create([
                'tenant_id' => $guard->tenant_id,
                'patrol_id' => $patrol->id,
                'route_checkpoint_id' => $rc->id,
                'checkpoint_id' => $rc->checkpoint_id,
                'guard_id' => $guard->id,
                'status' => 'pending',
                'position' => $rc->position,
                'recorded_offline' => false,
            ]);
        }

        return response()->json([
            'message' => 'Patrol session started successfully.',
            'patrol' => $patrol->load(['checkpointLogs.checkpoint', 'route']),
        ]);
    }

    /**
     * Scan a specific checkpoint inside an active patrol.
     */
    public function scanCheckpoint(Request $request, $patrolId, $routeCheckpointId): JsonResponse
    {
        $request->validate([
            'scan_method' => 'required|in:qr,nfc',
            'latitude' => 'required|numeric|between:-90,90',
            'longitude' => 'required|numeric|between:-180,180',
            'note' => 'nullable|string',
            'media_file' => 'nullable|file|max:10240', // 10MB limit
            'signature_file' => 'nullable|file|max:5120', // 5MB limit
        ]);

        $guard = $request->user();

        // Verify active patrol session
        $patrol = Patrol::where('id', $patrolId)
            ->where('guard_id', $guard->id)
            ->where('status', 'in_progress')
            ->first();

        if (!$patrol) {
            return response()->json([
                'message' => 'Active patrol session not found.',
            ], 404);
        }

        // Find route checkpoint config
        $routeCheckpoint = RouteCheckpoint::where('id', $routeCheckpointId)
            ->where('route_id', $patrol->route_id)
            ->first();

        if (!$routeCheckpoint) {
            return response()->json([
                'message' => 'Route checkpoint config not found.',
            ], 404);
        }

        // Get checkpoint master record
        $checkpoint = Checkpoint::find($routeCheckpoint->checkpoint_id);

        // Fetch corresponding log
        $log = PatrolCheckpointLog::where('patrol_id', $patrol->id)
            ->where('route_checkpoint_id', $routeCheckpoint->id)
            ->first();

        if (!$log) {
            return response()->json([
                'message' => 'Checkpoint log record not found.',
            ], 404);
        }

        if ($log->status === 'scanned') {
            return response()->json([
                'message' => 'This checkpoint has already been scanned.',
            ], 422);
        }

        // 1. Check strict order compliance
        $route = Route::find($patrol->route_id);
        if ($route->enforce_order) {
            // Check if any previous checkpoint is still pending
            $incompletePrevious = PatrolCheckpointLog::where('patrol_id', $patrol->id)
                ->where('position', '<', $log->position)
                ->whereIn('status', ['pending', 'out_of_order_attempt'])
                ->exists();

            if ($incompletePrevious) {
                // Log the out-of-order scan attempt
                $log->update([
                    'status' => 'out_of_order_attempt',
                    'attempted_position' => $log->position,
                ]);

                return response()->json([
                    'message' => 'Strict order enforced. Please complete previous checkpoints first.',
                ], 422);
            }
        }

        // 2. Validate scan method compatibility
        $allowedMethod = $checkpoint->scan_method; // qr, nfc, both
        if ($allowedMethod !== 'both' && $allowedMethod !== $request->scan_method) {
            return response()->json([
                'message' => "Invalid scan method. Expected: {$allowedMethod}.",
            ], 422);
        }

        // 3. Geofencing calculations (Haversine formula)
        $distance = null;
        $withinFence = true;

        if ($checkpoint->latitude && $checkpoint->longitude) {
            $distance = $this->calculateDistance(
                (double) $request->latitude,
                (double) $request->longitude,
                (double) $checkpoint->latitude,
                (double) $checkpoint->longitude
            );

            $fenceLimit = $checkpoint->gps_fence_radius;
            $withinFence = $distance <= $fenceLimit;
        }

        // If GPS is required but we have no coordinates, flag it
        if ($checkpoint->gps_required && is_null($distance)) {
            $withinFence = false;
        }

        // Update the log entry
        $log->update([
            'status' => 'scanned',
            'scan_method_used' => $request->scan_method,
            'scanned_at' => now(),
            'scan_latitude' => $request->latitude,
            'scan_longitude' => $request->longitude,
            'gps_distance_metres' => $distance,
            'gps_within_fence' => $withinFence,
            'note' => $request->note,
        ]);

        // 4. Handle media file upload
        if ($request->hasFile('media_file')) {
            $file = $request->file('media_file');
            // Store file securely in a public location
            $path = $file->store("tenants/{$guard->tenant_id}/patrols/{$patrol->id}", 'public');

            CheckpointMedia::create([
                'tenant_id' => $guard->tenant_id,
                'patrol_checkpoint_log_id' => $log->id,
                'patrol_id' => $patrol->id,
                'guard_id' => $guard->id,
                'kind' => 'photo', // default to photo, could detect mime type to set voice_memo
                'file_url' => asset('storage/' . $path),
                'file_key' => $path,
                'file_size_bytes' => $file->getSize(),
                'mime_type' => $file->getMimeType(),
                'captured_at' => now(),
                'capture_latitude' => $request->latitude,
                'capture_longitude' => $request->longitude,
                'recorded_offline' => false,
            ]);
        }

        // 4.1 Handle signature file upload
        if ($request->hasFile('signature_file')) {
            $sigFile = $request->file('signature_file');
            $sigPath = $sigFile->store("tenants/{$guard->tenant_id}/patrols/{$patrol->id}/signatures", 'public');

            CheckpointMedia::create([
                'tenant_id' => $guard->tenant_id,
                'patrol_checkpoint_log_id' => $log->id,
                'patrol_id' => $patrol->id,
                'guard_id' => $guard->id,
                'kind' => 'signature',
                'file_url' => asset('storage/' . $sigPath),
                'file_key' => $sigPath,
                'file_size_bytes' => $sigFile->getSize(),
                'mime_type' => $sigFile->getMimeType(),
                'captured_at' => now(),
                'capture_latitude' => $request->latitude,
                'capture_longitude' => $request->longitude,
                'recorded_offline' => false,
            ]);
        }

        // Update completed checkpoints counter in patrol session
        $patrol->increment('completed_checkpoints');

        return response()->json([
            'message' => 'Checkpoint scanned successfully.',
            'log' => $log->load('media'),
            'within_fence' => $withinFence,
            'distance_metres' => $distance,
        ]);
    }

    /**
     * Skip a checkpoint in the sequence (if allowed by route settings).
     */
    public function skipCheckpoint(Request $request, $patrolId, $routeCheckpointId): JsonResponse
    {
        $request->validate([
            'skip_reason' => 'required|string',
        ]);

        $guard = $request->user();

        // Verify active patrol session
        $patrol = Patrol::where('id', $patrolId)
            ->where('guard_id', $guard->id)
            ->where('status', 'in_progress')
            ->first();

        if (!$patrol) {
            return response()->json([
                'message' => 'Active patrol session not found.',
            ], 404);
        }

        // Check skip permission setting on the route
        $route = Route::find($patrol->route_id);
        if (!$route->allow_skip) {
            return response()->json([
                'message' => 'Skipping checkpoints is disabled for this route.',
            ], 403);
        }

        // Fetch corresponding log
        $log = PatrolCheckpointLog::where('patrol_id', $patrol->id)
            ->where('route_checkpoint_id', $routeCheckpointId)
            ->first();

        if (!$log) {
            return response()->json([
                'message' => 'Checkpoint log record not found.',
            ], 404);
        }

        if ($log->status === 'scanned' || $log->status === 'skipped') {
            return response()->json([
                'message' => 'This checkpoint is already processed.',
            ], 422);
        }

        // Check strict order compliance
        if ($route->enforce_order) {
            $incompletePrevious = PatrolCheckpointLog::where('patrol_id', $patrol->id)
                ->where('position', '<', $log->position)
                ->whereIn('status', ['pending', 'out_of_order_attempt'])
                ->exists();

            if ($incompletePrevious) {
                return response()->json([
                    'message' => 'Strict order enforced. Please complete previous checkpoints first.',
                ], 422);
            }
        }

        // Update status to skipped
        $log->update([
            'status' => 'skipped',
            'skip_reason' => $request->skip_reason,
            'skipped_at' => now(),
        ]);

        $patrol->increment('skipped_checkpoints');

        return response()->json([
            'message' => 'Checkpoint skipped successfully.',
            'log' => $log,
        ]);
    }

    /**
     * Add a general note covering the entire patrol.
     */
    public function addGeneralNote(Request $request, $patrolId): JsonResponse
    {
        $request->validate([
            'general_note' => 'required|string',
        ]);

        $guard = $request->user();

        $patrol = Patrol::where('id', $patrolId)
            ->where('guard_id', $guard->id)
            ->where('status', 'in_progress')
            ->first();

        if (!$patrol) {
            return response()->json([
                'message' => 'Active patrol session not found.',
            ], 404);
        }

        $patrol->update([
            'general_note' => $request->general_note,
        ]);

        return response()->json([
            'message' => 'General note updated.',
            'patrol' => $patrol,
        ]);
    }

    /**
     * Complete the patrol session.
     */
    public function completePatrol(Request $request, $patrolId): JsonResponse
    {
        $request->validate([
            'general_note' => 'nullable|string',
            'completion_signature' => 'nullable|file|max:5120',
            'completion_latitude' => 'nullable|numeric|between:-90,90',
            'completion_longitude' => 'nullable|numeric|between:-180,180',
        ]);

        $guard = $request->user();

        $patrol = Patrol::where('id', $patrolId)
            ->where('guard_id', $guard->id)
            ->where('status', 'in_progress')
            ->first();

        if (!$patrol) {
            return response()->json([
                'message' => 'Active patrol session not found.',
            ], 404);
        }

        // Check if there are any remaining pending checkpoints
        $pendingCount = PatrolCheckpointLog::where('patrol_id', $patrol->id)
            ->whereIn('status', ['pending', 'out_of_order_attempt'])
            ->count();

        // Complete the patrol
        $startedAt = $patrol->started_at;
        $completedAt = now();
        $duration = max(0, (int) $completedAt->diffInSeconds($startedAt));

        $signaturePath = null;
        if ($request->hasFile('completion_signature')) {
            $file = $request->file('completion_signature');
            $signaturePath = $file->store("tenants/{$guard->tenant_id}/patrols/{$patrol->id}/signatures", 'public');
        }

        $patrol->update([
            'status' => 'completed',
            'completed_at' => $completedAt,
            'duration_seconds' => $duration,
            'general_note' => $request->general_note ?? $patrol->general_note,
            'completed_by_guard' => true,
            'completion_latitude' => $request->completion_latitude,
            'completion_longitude' => $request->completion_longitude,
            'completion_signature_url' => $signaturePath ? asset('storage/' . $signaturePath) : null,
        ]);

        return response()->json([
            'message' => 'Patrol completed successfully.',
            'pending_checkpoints_ignored' => $pendingCount,
            'patrol' => $patrol->load('checkpointLogs'),
        ]);
    }

    /**
     * Haversine formula calculation for coordinates in meters.
     */
    private function calculateDistance(float $lat1, float $lon1, float $lat2, float $lon2): float
    {
        $earthRadius = 6371000; // meters

        $latDelta = deg2rad($lat2 - $lat1);
        $lonDelta = deg2rad($lon2 - $lon1);

        $a = sin($latDelta / 2) * sin($latDelta / 2) +
             cos(deg2rad($lat1)) * cos(deg2rad($lat2)) *
             sin($lonDelta / 2) * sin($lonDelta / 2);

        $c = 2 * atan2(sqrt($a), sqrt(1 - $a));

        return $earthRadius * $c;
    }
}
