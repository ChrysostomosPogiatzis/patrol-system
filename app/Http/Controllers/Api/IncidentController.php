<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Incident;
use App\Models\IncidentMedia;
use App\Models\Patrol;
use App\Models\PatrolCheckpointLog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class IncidentController extends Controller
{
    /**
     * Report an incident during an active patrol session.
     */
    public function reportIncident(Request $request, $patrolId): JsonResponse
    {
        $request->validate([
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'priority' => 'required|in:low,medium,high,critical',
            'latitude' => 'required|numeric|between:-90,90',
            'longitude' => 'required|numeric|between:-180,180',
            'patrol_checkpoint_log_id' => 'nullable|exists:patrol_checkpoint_logs,id',
            'media_files' => 'nullable|array',
            'media_files.*' => 'file|max:10240', // 10MB limit per file
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

        $checkpointId = null;
        $locationId = null;

        // If log ID is provided, retrieve checkpoint and location contexts
        if ($request->patrol_checkpoint_log_id) {
            $log = PatrolCheckpointLog::find($request->patrol_checkpoint_log_id);
            if ($log) {
                $checkpointId = $log->checkpoint_id;
                $locationId = $log->checkpoint->location_id ?? null;
            }
        }

        // Create the Incident record
        $incident = Incident::create([
            'tenant_id' => $guard->tenant_id,
            'patrol_id' => $patrol->id,
            'patrol_checkpoint_log_id' => $request->patrol_checkpoint_log_id,
            'checkpoint_id' => $checkpointId,
            'location_id' => $locationId,
            'guard_id' => $guard->id,
            'title' => $request->title,
            'description' => $request->description,
            'priority' => $request->priority,
            'status' => 'open',
            'incident_latitude' => $request->latitude,
            'incident_longitude' => $request->longitude,
            'recorded_offline' => false,
        ]);

        // Process uploaded attachments
        if ($request->hasFile('media_files')) {
            foreach ($request->file('media_files') as $file) {
                $path = $file->store("tenants/{$guard->tenant_id}/incidents/{$incident->id}", 'public');
                $mime = $file->getMimeType();
                $kind = str_contains($mime, 'audio') ? 'voice_memo' : 'photo';

                IncidentMedia::create([
                    'tenant_id' => $guard->tenant_id,
                    'incident_id' => $incident->id,
                    'guard_id' => $guard->id,
                    'kind' => $kind,
                    'file_url' => asset('storage/' . $path),
                    'file_key' => $path,
                    'file_size_bytes' => $file->getSize(),
                    'mime_type' => $mime,
                    'captured_at' => now(),
                    'capture_latitude' => $request->latitude,
                    'capture_longitude' => $request->longitude,
                    'recorded_offline' => false,
                ]);
            }
        }

        // Increment incident counter for patrol session
        $patrol->increment('incident_count');

        return response()->json([
            'message' => 'Incident reported successfully.',
            'incident' => $incident->load('media'),
        ]);
    }

    /**
     * Report an incident standalone (not associated with an active patrol).
     */
    public function reportStandaloneIncident(Request $request): JsonResponse
    {
        $request->validate([
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'priority' => 'required|in:low,medium,high,critical',
            'latitude' => 'required|numeric|between:-90,90',
            'longitude' => 'required|numeric|between:-180,180',
            'location_id' => 'nullable|exists:locations,id',
            'media_files' => 'nullable|array',
            'media_files.*' => 'file|max:10240',
        ]);

        $guard = $request->user();

        $incident = Incident::create([
            'tenant_id' => $guard->tenant_id,
            'patrol_id' => null,
            'patrol_checkpoint_log_id' => null,
            'checkpoint_id' => null,
            'location_id' => $request->location_id,
            'guard_id' => $guard->id,
            'title' => $request->title,
            'description' => $request->description,
            'priority' => $request->priority,
            'status' => 'open',
            'incident_latitude' => $request->latitude,
            'incident_longitude' => $request->longitude,
            'recorded_offline' => false,
        ]);

        if ($request->hasFile('media_files')) {
            foreach ($request->file('media_files') as $file) {
                $path = $file->store("tenants/{$guard->tenant_id}/incidents/{$incident->id}", 'public');

                IncidentMedia::create([
                    'tenant_id' => $guard->tenant_id,
                    'incident_id' => $incident->id,
                    'guard_id' => $guard->id,
                    'kind' => 'photo',
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
        }

        return response()->json([
            'message' => 'Standalone incident reported successfully.',
            'incident' => $incident->load('media'),
        ]);
    }
}
