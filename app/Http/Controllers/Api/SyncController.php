<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Checkpoint;
use App\Models\CheckpointMedia;
use App\Models\Guard;
use App\Models\GuardLocationPing;
use App\Models\Incident;
use App\Models\IncidentMedia;
use App\Models\OfflineSyncQueue;
use App\Models\Patrol;
use App\Models\PatrolCheckpointLog;
use App\Models\Route;
use App\Models\RouteCheckpoint;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class SyncController extends Controller
{
    /**
     * Reconcile a batch of offline actions recorded on a guard's device.
     */
    public function syncQueue(Request $request): JsonResponse
    {
        $request->validate([
            'queue' => 'required|array',
            'queue.*.entity_type' => 'required|in:patrol,patrol_complete,patrol_checkpoint_log,incident,checkpoint_media,guard_location_ping',
            'queue.*.entity_id' => 'required|string',
            'queue.*.payload' => 'required|array',
            'queue.*.captured_at' => 'required|date',
        ]);

        $guard = $request->user();
        $results = [];

        // Sort items: 'patrol' goes first so patrols are created and mapped before scans/media reference them.
        $sortedQueue = collect($request->queue)->sortBy(function ($item) {
            return $item['entity_type'] === 'patrol' ? 0 : 1;
        })->all();

        $patrolIdMap = [];

        foreach ($sortedQueue as $item) {
            $entityType = $item['entity_type'];
            $entityId = $item['entity_id'];
            $payload = $item['payload'];
            $capturedAt = $item['captured_at'];

            // Translate client-side temporary patrol_id if mapped
            if (isset($payload['patrol_id']) && isset($patrolIdMap[$payload['patrol_id']])) {
                $payload['patrol_id'] = $patrolIdMap[$payload['patrol_id']];
            }

            // Validate that the patrol_id actually exists to prevent foreign key violations
            $patrolId = null;
            if (isset($payload['patrol_id'])) {
                if (\App\Models\Patrol::where('id', $payload['patrol_id'])->exists()) {
                    $patrolId = $payload['patrol_id'];
                }
            }

            // Initialize log in the sync queue table
            $syncRecord = OfflineSyncQueue::create([
                'tenant_id' => $guard->tenant_id,
                'guard_id' => $guard->id,
                'patrol_id' => $patrolId,
                'entity_type' => $entityType,
                'entity_id' => null,
                'payload' => $payload,
                'state' => 'syncing',
                'captured_at' => $capturedAt,
                'queued_at' => now(),
            ]);

            try {
                switch ($entityType) {
                    case 'patrol':
                        $realPatrolId = $this->processOfflinePatrol($payload, $guard, $capturedAt);
                        if (isset($payload['patrol_id'])) {
                            $patrolIdMap[$payload['patrol_id']] = $realPatrolId;
                        }
                        break;

                    case 'patrol_complete':
                        $this->processOfflinePatrolComplete($payload, $guard, $capturedAt);
                        break;

                    case 'patrol_checkpoint_log':
                        $this->processOfflineScan($payload, $guard, $capturedAt);
                        break;

                    case 'incident':
                        $this->processOfflineIncident($payload, $guard, $capturedAt);
                        break;

                    case 'checkpoint_media':
                        $this->processOfflineMedia($payload, $guard, $capturedAt);
                        break;

                    case 'guard_location_ping':
                        $this->processOfflinePing($payload, $guard, $capturedAt);
                        break;
                }

                $syncRecord->update([
                    'state' => 'synced',
                    'synced_at' => now(),
                    'patrol_id' => $syncRecord->patrol_id ?: ($patrolIdMap[$payload['patrol_id'] ?? ''] ?? null),
                ]);

                $results[] = [
                    'entity_id' => $entityId,
                    'status' => 'success',
                ];
            } catch (\Exception $e) {
                $syncRecord->update([
                    'state' => 'failed',
                    'last_error' => $e->getMessage(),
                    'retry_count' => $syncRecord->retry_count + 1,
                ]);

                $results[] = [
                    'entity_id' => $entityId,
                    'status' => 'failed',
                    'error' => $e->getMessage(),
                ];
            }
        }

        return response()->json([
            'message' => 'Sync queue processed.',
            'results' => $results,
        ]);
    }

    /**
     * Process an offline checkpoint scan.
     */
    private function processOfflineScan(array $payload, Guard $guard, string $capturedAt): void
    {
        $patrolId = $payload['patrol_id'] ?? null;
        $routeCheckpointId = $payload['route_checkpoint_id'] ?? null;

        $patrol = Patrol::where('id', $patrolId)
            ->where('tenant_id', $guard->tenant_id)
            ->first();

        if (!$patrol) {
            throw new \Exception("Active patrol session {$patrolId} not found.");
        }

        $log = PatrolCheckpointLog::where('patrol_id', $patrol->id)
            ->where('route_checkpoint_id', $routeCheckpointId)
            ->first();

        if (!$log) {
            throw new \Exception("Checkpoint log not found for route checkpoint {$routeCheckpointId}.");
        }

        if ($log->status === 'scanned') {
            return; // Already synced/processed
        }

        $checkpoint = Checkpoint::find($log->checkpoint_id);
        $distance = null;
        $withinFence = true;

        if ($checkpoint->latitude && $checkpoint->longitude && isset($payload['latitude']) && isset($payload['longitude'])) {
            $distance = $this->calculateDistance(
                (float) $payload['latitude'],
                (float) $payload['longitude'],
                (float) $checkpoint->latitude,
                (float) $checkpoint->longitude
            );
            $withinFence = $distance <= $checkpoint->gps_fence_radius;
        }

        if ($checkpoint->gps_required && is_null($distance)) {
            $withinFence = false;
        }

        if ($payload['status'] === 'skipped') {
            $log->update([
                'status' => 'skipped',
                'skip_reason' => $payload['skip_reason'] ?? 'Skipped offline',
                'skipped_at' => $capturedAt,
                'recorded_offline' => true,
                'device_timestamp' => $capturedAt,
                'synced_at' => now(),
            ]);
            $patrol->increment('skipped_checkpoints');
        } else {
            $log->update([
                'status' => 'scanned',
                'scan_method_used' => $payload['scan_method'] ?? 'qr',
                'scanned_at' => $capturedAt,
                'scan_latitude' => $payload['latitude'] ?? null,
                'scan_longitude' => $payload['longitude'] ?? null,
                'gps_distance_metres' => $distance,
                'gps_within_fence' => $withinFence,
                'note' => $payload['note'] ?? null,
                'battery_pct' => $payload['battery_pct'] ?? null,
                'recorded_offline' => true,
                'device_timestamp' => $capturedAt,
                'synced_at' => now(),
            ]);
            $patrol->increment('completed_checkpoints');
        }

        // Set patrol to in_progress or was_offline = true
        $patrol->update(['was_offline' => true]);
    }

    /**
     * Process an offline incident report.
     */
    private function processOfflineIncident(array $payload, Guard $guard, string $capturedAt): void
    {
        $patrolId = $payload['patrol_id'] ?? null;
        $patrol = null;

        if ($patrolId) {
            $patrol = Patrol::find($patrolId);
        }

        $incident = Incident::create([
            'tenant_id' => $guard->tenant_id,
            'patrol_id' => $patrol ? $patrol->id : null,
            'patrol_checkpoint_log_id' => $payload['patrol_checkpoint_log_id'] ?? null,
            'checkpoint_id' => $payload['checkpoint_id'] ?? null,
            'location_id' => $payload['location_id'] ?? null,
            'guard_id' => $guard->id,
            'title' => $payload['title'] ?? 'Offline Incident',
            'description' => $payload['description'] ?? null,
            'priority' => $payload['priority'] ?? 'medium',
            'status' => 'open',
            'incident_latitude' => $payload['latitude'] ?? null,
            'incident_longitude' => $payload['longitude'] ?? null,
            'recorded_offline' => true,
            'device_timestamp' => $capturedAt,
            'synced_at' => now(),
        ]);

        if ($patrol) {
            $patrol->increment('incident_count');
        }

        // Decode base64 image data if provided offline
        if (isset($payload['base64_media']) && is_array($payload['base64_media'])) {
            foreach ($payload['base64_media'] as $mediaItem) {
                $base64Data = $mediaItem['data'] ?? '';
                if ($base64Data) {
                    $decoded = base64_decode($base64Data);
                    $filename = uniqid() . '_' . ($mediaItem['filename'] ?? 'photo.jpg');
                    $path = "tenants/{$guard->tenant_id}/incidents/{$incident->id}/{$filename}";
                    Storage::disk('public')->put($path, $decoded);

                    $mime = $mediaItem['mime_type'] ?? 'image/jpeg';
                    $kind = (str_contains($mime, 'audio') || str_contains($filename, 'voice') || str_contains($filename, 'audio')) ? 'voice_memo' : 'photo';

                    IncidentMedia::create([
                        'tenant_id' => $guard->tenant_id,
                        'incident_id' => $incident->id,
                        'guard_id' => $guard->id,
                        'kind' => $kind,
                        'file_url' => asset('storage/' . $path),
                        'file_key' => $path,
                        'file_size_bytes' => strlen($decoded),
                        'captured_at' => $capturedAt,
                        'capture_latitude' => $payload['latitude'] ?? null,
                        'capture_longitude' => $payload['longitude'] ?? null,
                        'recorded_offline' => true,
                        'synced_at' => now(),
                    ]);
                }
            }
        }
    }

    /**
     * Process offline checkpoint media files.
     */
    private function processOfflineMedia(array $payload, Guard $guard, string $capturedAt): void
    {
        $logId = $payload['patrol_checkpoint_log_id'] ?? null;
        $log = PatrolCheckpointLog::find($logId);

        if (!$log) {
            throw new \Exception("Checkpoint log {$logId} not found for media sync.");
        }

        $base64Data = $payload['base64_data'] ?? '';
        if ($base64Data) {
            $decoded = base64_decode($base64Data);
            $filename = uniqid() . '_' . ($payload['filename'] ?? 'photo.jpg');
            $path = "tenants/{$guard->tenant_id}/patrols/{$log->patrol_id}/{$filename}";
            Storage::disk('public')->put($path, $decoded);

            CheckpointMedia::create([
                'tenant_id' => $guard->tenant_id,
                'patrol_checkpoint_log_id' => $log->id,
                'patrol_id' => $log->patrol_id,
                'guard_id' => $guard->id,
                'kind' => $payload['kind'] ?? 'photo',
                'file_url' => asset('storage/' . $path),
                'file_key' => $path,
                'file_size_bytes' => strlen($decoded),
                'mime_type' => $payload['mime_type'] ?? 'image/jpeg',
                'duration_seconds' => $payload['duration_seconds'] ?? null,
                'captured_at' => $capturedAt,
                'capture_latitude' => $payload['latitude'] ?? null,
                'capture_longitude' => $payload['longitude'] ?? null,
                'recorded_offline' => true,
                'synced_at' => now(),
            ]);
        }
    }

    /**
     * Process an offline background location ping.
     */
    private function processOfflinePing(array $payload, Guard $guard, string $capturedAt): void
    {
        GuardLocationPing::create([
            'tenant_id' => $guard->tenant_id,
            'guard_id' => $guard->id,
            'patrol_id' => $payload['patrol_id'] ?? null,
            'latitude' => $payload['latitude'] ?? 0.0,
            'longitude' => $payload['longitude'] ?? 0.0,
            'accuracy_m' => $payload['accuracy_m'] ?? null,
            'battery_pct' => $payload['battery_pct'] ?? null,
            'is_online' => false,
            'recorded_offline' => true,
            'device_timestamp' => $capturedAt,
            'pinged_at' => now(),
        ]);
    }

    /**
     * Haversine distance calculator.
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

    /**
     * Process an offline patrol starting event.
     */
    private function processOfflinePatrol(array $payload, Guard $guard, string $capturedAt): int
    {
        $routeId = $payload['route_id'] ?? null;
        $route = Route::where('id', $routeId)->where('is_active', true)->first();

        if (!$route) {
            throw new \Exception("Active route {$routeId} not found.");
        }

        // Initialize offline patrol record
        $patrol = Patrol::create([
            'tenant_id' => $guard->tenant_id,
            'route_id' => $route->id,
            'guard_id' => $guard->id,
            'status' => 'in_progress',
            'started_at' => $payload['started_at'] ?? $capturedAt,
            'total_checkpoints' => RouteCheckpoint::where('route_id', $route->id)->count(),
            'completed_checkpoints' => 0,
            'skipped_checkpoints' => 0,
            'incident_count' => 0,
            'was_offline' => true,
        ]);

        $routeCheckpoints = RouteCheckpoint::where('route_id', $route->id)->orderBy('position')->get();
        foreach ($routeCheckpoints as $rc) {
            PatrolCheckpointLog::create([
                'tenant_id' => $guard->tenant_id,
                'patrol_id' => $patrol->id,
                'route_checkpoint_id' => $rc->id,
                'checkpoint_id' => $rc->checkpoint_id,
                'guard_id' => $guard->id,
                'status' => 'pending',
                'position' => $rc->position,
                'recorded_offline' => true,
            ]);
        }

        return $patrol->id;
    }

    /**
     * Process an offline patrol completion event.
     */
    private function processOfflinePatrolComplete(array $payload, Guard $guard, string $capturedAt): void
    {
        $patrolId = $payload['patrol_id'] ?? null;
        $patrol = Patrol::where('id', $patrolId)->where('tenant_id', $guard->tenant_id)->first();

        if (!$patrol) {
            throw new \Exception("Patrol {$patrolId} not found for offline completion.");
        }

        $patrol->update([
            'status' => 'completed',
            'completed_at' => $payload['completed_at'] ?? $capturedAt,
            'completion_latitude' => $payload['completion_latitude'] ?? null,
            'completion_longitude' => $payload['completion_longitude'] ?? null,
            'general_note' => $payload['general_note'] ?? null,
        ]);

        $sigBase64 = $payload['signature_base64'] ?? '';
        if ($sigBase64 && strlen($sigBase64) > 1500) {
            $decoded = base64_decode(explode(',', $sigBase64)[1] ?? $sigBase64);
            $filename = uniqid() . '_completion_signature.png';
            $path = "tenants/{$guard->tenant_id}/patrols/{$patrol->id}/signatures/{$filename}";
            Storage::disk('public')->put($path, $decoded);

            CheckpointMedia::create([
                'tenant_id' => $guard->tenant_id,
                'patrol_checkpoint_log_id' => null,
                'patrol_id' => $patrol->id,
                'guard_id' => $guard->id,
                'kind' => 'signature',
                'file_url' => asset('storage/' . $path),
                'file_key' => $path,
                'file_size_bytes' => strlen($decoded),
                'mime_type' => 'image/png',
                'captured_at' => $capturedAt,
                'capture_latitude' => $payload['completion_latitude'] ?? null,
                'capture_longitude' => $payload['completion_longitude'] ?? null,
                'recorded_offline' => true,
                'synced_at' => now(),
            ]);
        }
    }
}
