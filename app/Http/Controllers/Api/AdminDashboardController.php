<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Checkpoint;
use App\Models\Guard;
use App\Models\Incident;
use App\Models\Location;
use App\Models\Patrol;
use App\Models\PatrolContact;
use App\Models\Route;
use App\Models\RouteCheckpoint;
use App\Models\SosAlert;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

class AdminDashboardController extends Controller
{
    /**
     * Get real-time overview stats and feed.
     */
    public function overview(): JsonResponse
    {
        $tenantId = Auth::user()->tenant_id;

        $stats = [
            'locations_count' => Location::count(),
            'guards_count' => Guard::where('is_active', true)->count(),
            'active_patrols_count' => Patrol::where('status', 'in_progress')->count(),
            'incidents_today_count' => Incident::whereDate('created_at', now()->toDateString())->count(),
            'active_sos_count' => SosAlert::where('status', 'active')->count(),
        ];

        $activePatrols = Patrol::where('status', 'in_progress')
            ->with(['securityGuard', 'route', 'tenant'])
            ->orderBy('started_at', 'desc')
            ->get();

        $recentIncidents = Incident::with(['securityGuard', 'checkpoint', 'location', 'tenant'])
            ->orderBy('id', 'desc')
            ->take(10)
            ->get();

        $activeSos = SosAlert::where('status', 'active')
            ->with(['securityGuard', 'tenant'])
            ->orderBy('triggered_at', 'desc')
            ->get();

        $guardLocations = \App\Models\GuardLocationPing::with(['securityGuard', 'tenant'])
            ->orderBy('id', 'desc')
            ->get()
            ->unique('guard_id')
            ->values();

        $locations = Location::with('checkpoints')->get();

        return response()->json([
            'stats' => $stats,
            'active_patrols' => $activePatrols,
            'recent_incidents' => $recentIncidents,
            'active_sos' => $activeSos,
            'guard_locations' => $guardLocations,
            'locations' => $locations,
        ]);
    }

    /**
     * Switch active tenant context for Super Admin.
     */
    public function switchTenant(Request $request): JsonResponse
    {
        if (Auth::user()->role !== 'superadmin') {
            return response()->json(['message' => 'Unauthorized.'], 403);
        }

        $request->validate([
            'tenant_id' => 'nullable|exists:tenants,id',
        ]);

        if (is_null($request->tenant_id)) {
            session()->forget('override_tenant_id');
            return response()->json(['message' => 'Switched to All Companies console.']);
        }

        session()->put('override_tenant_id', (int) $request->tenant_id);

        return response()->json(['message' => 'Tenant context overridden.']);
    }

    /**
     * List all guards under this tenant.
     */
    public function listGuards(): JsonResponse
    {
        $guards = Guard::orderBy('id', 'desc')->get();
        return response()->json(['guards' => $guards]);
    }

    /**
     * Create a new guard.
     */
    public function createGuard(Request $request): JsonResponse
    {
        $request->validate([
            'full_name' => 'required|string|max:255',
            'phone' => 'required|string|unique:guards,phone',
            'employee_id' => 'required|string|max:50|unique:guards,employee_id',
            'pin' => 'required|string|min:4|max:6',
        ]);

        $tenantId = Auth::user()->tenant_id;

        $guard = Guard::create([
            'tenant_id' => $tenantId,
            'full_name' => $request->full_name,
            'phone' => $request->phone,
            'employee_id' => $request->employee_id,
            'pin' => bcrypt($request->pin),
            'is_active' => true,
        ]);

        return response()->json([
            'message' => 'Guard registered successfully.',
            'guard' => $guard,
        ]);
    }

    /**
     * Get locations, checkpoints, and routes definitions.
     */
    public function locationsData(): JsonResponse
    {
        $locations = Location::with('checkpoints')->get();
        $checkpoints = Checkpoint::with('location')->get();
        $routes = Route::with(['routeCheckpoints.checkpoint'])->get();

        return response()->json([
            'locations' => $locations,
            'checkpoints' => $checkpoints,
            'routes' => $routes,
        ]);
    }

    /**
     * Create a location.
     */
    public function createLocation(Request $request): JsonResponse
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'address' => 'nullable|string',
            'city' => 'nullable|string',
            'country' => 'nullable|string',
            'latitude' => 'required|numeric|between:-90,90',
            'longitude' => 'required|numeric|between:-180,180',
            'geofence_radius' => 'required|integer|min:10',
        ]);

        $tenantId = Auth::user()->tenant_id;

        $location = Location::create(array_merge($request->all(), [
            'tenant_id' => $tenantId,
            'is_active' => true,
        ]));

        return response()->json([
            'message' => 'Location created successfully.',
            'location' => $location,
        ]);
    }

    /**
     * Create a checkpoint.
     */
    public function createCheckpoint(Request $request): JsonResponse
    {
        $request->validate([
            'location_id' => 'required|exists:locations,id',
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'scan_method' => 'required|in:qr,nfc,both',
            'qr_code' => 'nullable|string',
            'nfc_tag_id' => 'nullable|string',
            'gps_required' => 'required|boolean',
            'gps_fence_radius' => 'required|integer|min:5',
            'latitude' => 'nullable|numeric|between:-90,90',
            'longitude' => 'nullable|numeric|between:-180,180',
            'photo_requirement' => 'required|in:off,optional,required',
            'note_requirement' => 'required|in:off,optional,required',
            'voice_requirement' => 'required|in:off,optional,required',
            'signature_required' => 'required|boolean',
        ]);

        $tenantId = Auth::user()->tenant_id;

        $checkpoint = Checkpoint::create(array_merge($request->all(), [
            'tenant_id' => $tenantId,
            'is_active' => true,
            'qr_code' => $request->qr_code ?: Str::upper(Str::random(10)),
        ]));

        return response()->json([
            'message' => 'Checkpoint created successfully.',
            'checkpoint' => $checkpoint,
        ]);
    }

    /**
     * Create a route.
     */
    public function createRoute(Request $request): JsonResponse
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'enforce_order' => 'required|boolean',
            'allow_skip' => 'required|boolean',
            'expected_duration_mins' => 'nullable|integer|min:1',
            'checkpoints' => 'required|array|min:1',
            'checkpoints.*' => 'exists:checkpoints,id',
        ]);

        $tenantId = Auth::user()->tenant_id;

        $route = Route::create([
            'tenant_id' => $tenantId,
            'name' => $request->name,
            'description' => $request->description,
            'enforce_order' => $request->enforce_order,
            'allow_skip' => $request->allow_skip,
            'expected_duration_mins' => $request->expected_duration_mins,
            'is_active' => true,
        ]);

        foreach ($request->checkpoints as $index => $checkpointId) {
            RouteCheckpoint::create([
                'route_id' => $route->id,
                'checkpoint_id' => $checkpointId,
                'position' => $index + 1,
            ]);
        }

        return response()->json([
            'message' => 'Patrol route created successfully.',
            'route' => $route->load('routeCheckpoints.checkpoint'),
        ]);
    }

    /**
     * Resolve/Acknowledge an incident.
     */
    public function resolveIncident(Request $request, $id): JsonResponse
    {
        $request->validate([
            'resolution_note' => 'required|string',
        ]);

        $incident = Incident::findOrFail($id);
        $incident->update([
            'status' => 'resolved',
            'resolved_by' => Auth::id(),
            'resolved_at' => now(),
            'resolution_note' => $request->resolution_note,
        ]);

        return response()->json([
            'message' => 'Incident marked as resolved.',
            'incident' => $incident,
        ]);
    }

    /**
     * Resolve/Acknowledge an SOS alert.
     */
    public function resolveSos(Request $request, $id): JsonResponse
    {
        $request->validate([
            'resolution_note' => 'required|string',
        ]);

        $sos = SosAlert::findOrFail($id);
        $sos->update([
            'status' => 'resolved',
            'resolved_by' => Auth::id(),
            'resolved_at' => now(),
            'resolution_note' => $request->resolution_note,
        ]);

        return response()->json([
            'message' => 'SOS Alert marked as resolved.',
            'sos_alert' => $sos,
        ]);
    }

    /**
     * List emergency contacts.
     */
    public function listContacts(): JsonResponse
    {
        $contacts = PatrolContact::orderBy('id', 'desc')->get();
        return response()->json(['contacts' => $contacts]);
    }

    /**
     * Save emergency contact.
     */
    public function createContact(Request $request): JsonResponse
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'role_label' => 'required|string|max:100',
            'phone' => 'nullable|string',
            'email' => 'nullable|email',
            'notify_channels' => 'required|array',
            'notify_on' => 'required|array',
        ]);

        $tenantId = Auth::user()->tenant_id;

        $contact = PatrolContact::create([
            'tenant_id' => $tenantId,
            'name' => $request->name,
            'role_label' => $request->role_label,
            'phone' => $request->phone,
            'email' => $request->email,
            'notify_channels' => $request->notify_channels,
            'notify_on' => $request->notify_on,
            'is_active' => true,
        ]);

        return response()->json([
            'message' => 'Emergency contact added successfully.',
            'contact' => $contact,
        ]);
    }

    /**
     * Update an existing guard.
     */
    public function updateGuard(Request $request, $id): JsonResponse
    {
        $guard = Guard::findOrFail($id);

        $request->validate([
            'full_name' => 'required|string|max:255',
            'phone' => 'required|string|unique:guards,phone,' . $guard->id,
            'employee_id' => 'required|string|max:50|unique:guards,employee_id,' . $guard->id,
            'pin' => 'nullable|string|min:4|max:6',
        ]);

        $updateData = [
            'full_name' => $request->full_name,
            'phone' => $request->phone,
            'employee_id' => $request->employee_id,
        ];

        if ($request->filled('pin') && $request->pin !== '1234') {
            $updateData['pin'] = bcrypt($request->pin);
        }

        $guard->update($updateData);

        return response()->json([
            'message' => 'Guard updated successfully.',
            'guard' => $guard,
        ]);
    }

    /**
     * Delete a guard.
     */
    public function deleteGuard($id): JsonResponse
    {
        $guard = Guard::findOrFail($id);
        $guard->delete();

        return response()->json([
            'message' => 'Guard deleted successfully.',
        ]);
    }

    /**
     * Update an existing location.
     */
    public function updateLocation(Request $request, $id): JsonResponse
    {
        $location = Location::findOrFail($id);

        $request->validate([
            'name' => 'required|string|max:255',
            'address' => 'nullable|string',
            'city' => 'nullable|string',
            'country' => 'nullable|string',
            'latitude' => 'required|numeric|between:-90,90',
            'longitude' => 'required|numeric|between:-180,180',
            'geofence_radius' => 'required|integer|min:10',
        ]);

        $location->update($request->all());

        return response()->json([
            'message' => 'Location updated successfully.',
            'location' => $location,
        ]);
    }

    /**
     * Delete a location.
     */
    public function deleteLocation($id): JsonResponse
    {
        $location = Location::findOrFail($id);

        // Check if there are active checkpoints
        if ($location->checkpoints()->count() > 0) {
            return response()->json([
                'message' => 'Cannot delete location: it has active checkpoints configured.',
            ], 422);
        }

        $location->delete();

        return response()->json([
            'message' => 'Location deleted successfully.',
        ]);
    }

    /**
     * Update an existing checkpoint.
     */
    public function updateCheckpoint(Request $request, $id): JsonResponse
    {
        $checkpoint = Checkpoint::findOrFail($id);

        $request->validate([
            'location_id' => 'required|exists:locations,id',
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'scan_method' => 'required|in:qr,nfc,both',
            'qr_code' => 'nullable|string',
            'nfc_tag_id' => 'nullable|string',
            'gps_required' => 'required|boolean',
            'gps_fence_radius' => 'required|integer|min:5',
            'latitude' => 'nullable|numeric|between:-90,90',
            'longitude' => 'nullable|numeric|between:-180,180',
            'photo_requirement' => 'required|in:off,optional,required',
            'note_requirement' => 'required|in:off,optional,required',
            'voice_requirement' => 'required|in:off,optional,required',
            'signature_required' => 'required|boolean',
        ]);

        $checkpoint->update(array_merge($request->all(), [
            'qr_code' => $request->qr_code ?: Str::upper(Str::random(10)),
        ]));

        return response()->json([
            'message' => 'Checkpoint updated successfully.',
            'checkpoint' => $checkpoint,
        ]);
    }

    /**
     * Delete a checkpoint.
     */
    public function deleteCheckpoint($id): JsonResponse
    {
        $checkpoint = Checkpoint::findOrFail($id);
        $checkpoint->delete();

        return response()->json([
            'message' => 'Checkpoint deleted successfully.',
        ]);
    }

    /**
     * Update an existing route.
     */
    public function updateRoute(Request $request, $id): JsonResponse
    {
        $route = Route::findOrFail($id);

        $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'enforce_order' => 'required|boolean',
            'allow_skip' => 'required|boolean',
            'expected_duration_mins' => 'nullable|integer|min:1',
            'checkpoints' => 'required|array|min:1',
            'checkpoints.*' => 'exists:checkpoints,id',
        ]);

        $route->update([
            'name' => $request->name,
            'description' => $request->description,
            'enforce_order' => $request->enforce_order,
            'allow_skip' => $request->allow_skip,
            'expected_duration_mins' => $request->expected_duration_mins,
        ]);

        // Delete existing checkpoints sequence and re-create
        RouteCheckpoint::where('route_id', $route->id)->delete();

        foreach ($request->checkpoints as $index => $checkpointId) {
            RouteCheckpoint::create([
                'route_id' => $route->id,
                'checkpoint_id' => $checkpointId,
                'position' => $index + 1,
            ]);
        }

        return response()->json([
            'message' => 'Patrol route updated successfully.',
            'route' => $route->load('routeCheckpoints.checkpoint'),
        ]);
    }

    /**
     * Delete a route.
     */
    public function deleteRoute($id): JsonResponse
    {
        $route = Route::findOrFail($id);
        
        RouteCheckpoint::where('route_id', $route->id)->delete();
        $route->delete();

        return response()->json([
            'message' => 'Patrol route deleted successfully.',
        ]);
    }

    /**
     * Update an existing emergency contact.
     */
    public function updateContact(Request $request, $id): JsonResponse
    {
        $contact = PatrolContact::findOrFail($id);

        $request->validate([
            'name' => 'required|string|max:255',
            'role_label' => 'required|string|max:100',
            'phone' => 'nullable|string',
            'email' => 'nullable|email',
            'notify_channels' => 'required|array',
            'notify_on' => 'required|array',
        ]);

        $contact->update([
            'name' => $request->name,
            'role_label' => $request->role_label,
            'phone' => $request->phone,
            'email' => $request->email,
            'notify_channels' => $request->notify_channels,
            'notify_on' => $request->notify_on,
        ]);

        return response()->json([
            'message' => 'Emergency contact updated successfully.',
            'contact' => $contact,
        ]);
    }

    /**
     * Delete an emergency contact.
     */
    public function deleteContact($id): JsonResponse
    {
        $contact = PatrolContact::findOrFail($id);
        $contact->delete();

        return response()->json([
            'message' => 'Emergency contact deleted successfully.',
        ]);
    }

    /**
     * Get patrol and incident history logs.
     */
    public function historyData(Request $request): JsonResponse
    {
        $guardId = $request->input('guard_id');
        $timeframe = $request->input('timeframe', '7_days'); // today, yesterday, 7_days, 30_days, all

        // Date calculation
        $startDate = null;
        $endDate = now();

        if ($timeframe === 'today') {
            $startDate = now()->startOfDay();
        } elseif ($timeframe === 'yesterday') {
            $startDate = now()->subDay()->startOfDay();
            $endDate = now()->subDay()->endOfDay();
        } elseif ($timeframe === '7_days') {
            $startDate = now()->subDays(7)->startOfDay();
        } elseif ($timeframe === '30_days') {
            $startDate = now()->subDays(30)->startOfDay();
        }

        // Patrol Query - eager loading route, guard, and nested incidents with their checkpoints/guards
        $patrolQuery = Patrol::with([
            'securityGuard',
            'route',
            'tenant',
            'incidents.securityGuard',
            'incidents.checkpoint',
            'incidents.location',
            'checkpointLogs.checkpoint'
        ])->orderBy('started_at', 'desc');

        if ($guardId) {
            $patrolQuery->where('guard_id', $guardId);
        }
        if ($startDate) {
            $patrolQuery->whereBetween('started_at', [$startDate, $endDate]);
        }
        $patrols = $patrolQuery->get();

        // Incident Query for Standalone view
        $incidentQuery = Incident::with(['securityGuard', 'checkpoint', 'location', 'patrol', 'tenant'])
            ->orderBy('created_at', 'desc');

        if ($guardId) {
            $incidentQuery->where('guard_id', $guardId);
        }
        if ($startDate) {
            $incidentQuery->whereBetween('created_at', [$startDate, $endDate]);
        }
        $incidents = $incidentQuery->get();

        // Guards for selector
        $guards = Guard::orderBy('full_name')->get();

        return response()->json([
            'patrols' => $patrols,
            'incidents' => $incidents,
            'guards' => $guards
        ]);
    }
}
