<?php

namespace Tests\Feature;

use App\Models\Checkpoint;
use App\Models\Guard;
use App\Models\Location;
use App\Models\Patrol;
use App\Models\PatrolCheckpointLog;
use App\Models\Route;
use App\Models\RouteAssignment;
use App\Models\RouteCheckpoint;
use App\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class GuardApiTest extends TestCase
{
    use RefreshDatabase;

    protected Tenant $tenant;
    protected Guard $guard;
    protected Location $location;
    protected Checkpoint $checkpoint1;
    protected Checkpoint $checkpoint2;
    protected Route $route;
    protected RouteCheckpoint $rc1;
    protected RouteCheckpoint $rc2;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');

        // Setup base tenant and guard
        $this->tenant = Tenant::create([
            'name' => 'SaaS Sentinel Corp',
            'slug' => 'sentinel-corp',
        ]);

        $this->guard = Guard::create([
            'tenant_id' => $this->tenant->id,
            'full_name' => 'John Connor',
            'phone' => '+1234567890',
            'is_active' => true,
        ]);

        // Setup Location and Checkpoints
        $this->location = Location::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'HQ Facility',
            'latitude' => 35.123456,
            'longitude' => 33.123456,
        ]);

        // Checkpoint 1 (HQ Gate)
        $this->checkpoint1 = Checkpoint::create([
            'tenant_id' => $this->tenant->id,
            'location_id' => $this->location->id,
            'name' => 'Front Gate Checkpoint',
            'scan_method' => 'qr',
            'qr_code' => 'front-gate-qr-token-xyz',
            'gps_required' => true,
            'gps_fence_radius' => 20, // 20 meters
            'latitude' => 35.123456,
            'longitude' => 33.123456,
        ]);

        // Checkpoint 2 (HQ Office Door)
        $this->checkpoint2 = Checkpoint::create([
            'tenant_id' => $this->tenant->id,
            'location_id' => $this->location->id,
            'name' => 'Back Office Entry',
            'scan_method' => 'both',
            'qr_code' => 'back-office-qr-token',
            'nfc_tag_id' => 'nfc-id-office-999',
            'gps_required' => true,
            'gps_fence_radius' => 15, // 15 meters
            'latitude' => 35.123800, // Slightly offset
            'longitude' => 33.123800,
        ]);

        // Setup Route and join Checkpoints
        $this->route = Route::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Night Guard Patrol',
            'enforce_order' => true,
            'allow_skip' => true,
        ]);

        $this->rc1 = RouteCheckpoint::create([
            'route_id' => $this->route->id,
            'checkpoint_id' => $this->checkpoint1->id,
            'position' => 1,
        ]);

        $this->rc2 = RouteCheckpoint::create([
            'route_id' => $this->route->id,
            'checkpoint_id' => $this->checkpoint2->id,
            'position' => 2,
        ]);

        // Assign route to guard
        RouteAssignment::create([
            'tenant_id' => $this->tenant->id,
            'route_id' => $this->route->id,
            'guard_id' => $this->guard->id,
            'is_active' => true,
        ]);
    }

    public function test_guard_can_request_otp_and_login(): string
    {
        // 1. Request OTP
        $response = $this->postJson('/api/guard/otp/send', [
            'phone' => '+1234567890',
        ]);

        $response->assertStatus(200)
            ->assertJsonStructure(['otp']);

        $otp = $response->json('otp');

        // 2. Login with OTP
        $loginResponse = $this->postJson('/api/guard/login', [
            'phone' => '+1234567890',
            'otp' => $otp,
        ]);

        $loginResponse->assertStatus(200)
            ->assertJsonStructure(['token', 'guard']);

        return $loginResponse->json('token');
    }

    public function test_guard_patrol_operations(): void
    {
        $token = $this->test_guard_can_request_otp_and_login();
        $headers = ['Authorization' => "Bearer {$token}"];

        // 1. Fetch assigned routes
        $routesResponse = $this->getJson('/api/guard/routes', $headers);
        $routesResponse->assertStatus(200)
            ->assertJsonCount(1, 'routes');

        // 2. Start a patrol
        $startResponse = $this->postJson('/api/guard/patrols/start', [
            'route_id' => $this->route->id,
        ], $headers);

        $startResponse->assertStatus(200)
            ->assertJsonPath('patrol.status', 'in_progress');

        $patrolId = $startResponse->json('patrol.id');

        // 3. Scan Checkpoint 1 (within geofence)
        $scan1Response = $this->postJson("/api/guard/patrols/{$patrolId}/checkpoints/{$this->rc1->id}/scan", [
            'scan_method' => 'qr',
            'latitude' => 35.123456, // exact match
            'longitude' => 33.123456,
            'note' => 'Gate looks secure.',
            'media_file' => UploadedFile::fake()->image('photo1.jpg'),
        ], $headers);

        $scan1Response->assertStatus(200)
            ->assertJsonPath('within_fence', true)
            ->assertJsonPath('log.status', 'scanned');

        // 4. Scan Checkpoint 2 (outside geofence radius)
        $scan2Response = $this->postJson("/api/guard/patrols/{$patrolId}/checkpoints/{$this->rc2->id}/scan", [
            'scan_method' => 'nfc',
            'latitude' => 35.200000, // far away
            'longitude' => 33.200000,
            'note' => 'Office locked.',
        ], $headers);

        $scan2Response->assertStatus(200)
            ->assertJsonPath('within_fence', false)
            ->assertJsonPath('log.status', 'scanned');

        // 5. Submit general note
        $noteResponse = $this->postJson("/api/guard/patrols/{$patrolId}/note", [
            'general_note' => 'All quiet on the shift tonight.',
        ], $headers);
        $noteResponse->assertStatus(200);

        // 6. Complete patrol session
        $completeResponse = $this->postJson("/api/guard/patrols/{$patrolId}/complete", [
            'completion_latitude' => 35.123456,
            'completion_longitude' => 33.123456,
            'completion_signature' => UploadedFile::fake()->image('sig.png'),
        ], $headers);

        $completeResponse->assertStatus(200)
            ->assertJsonPath('patrol.status', 'completed');
    }

    public function test_guard_can_trigger_emergencies_and_pings(): void
    {
        $token = $this->test_guard_can_request_otp_and_login();
        $headers = ['Authorization' => "Bearer {$token}"];

        // 1. Test background location ping
        $pingResponse = $this->postJson('/api/guard/location/ping', [
            'latitude' => 35.123456,
            'longitude' => 33.123456,
            'accuracy_m' => 5.2,
            'battery_pct' => 88,
        ], $headers);

        $pingResponse->assertStatus(200);

        // 2. Test triggering emergency SOS
        $sosResponse = $this->postJson('/api/guard/sos/trigger', [
            'latitude' => 35.123456,
            'longitude' => 33.123456,
            'note' => 'Help required at the main warehouse.',
        ], $headers);

        $sosResponse->assertStatus(200)
            ->assertJsonPath('sos_alert.status', 'active');

        $sosId = $sosResponse->json('sos_alert.id');

        // 3. Stream location updates while SOS is active
        $sosPingResponse = $this->postJson("/api/guard/sos/{$sosId}/ping", [
            'latitude' => 35.123500,
            'longitude' => 33.123500,
            'accuracy_m' => 3.0,
        ], $headers);

        $sosPingResponse->assertStatus(200);
    }

    public function test_offline_sync_reconciliation(): void
    {
        $token = $this->test_guard_can_request_otp_and_login();
        $headers = ['Authorization' => "Bearer {$token}"];

        // 1. Create a patrol session
        $startResponse = $this->postJson('/api/guard/patrols/start', [
            'route_id' => $this->route->id,
        ], $headers);
        $patrolId = $startResponse->json('patrol.id');

        // 2. Compile offline queue sync batch
        $syncResponse = $this->postJson('/api/guard/sync', [
            'queue' => [
                [
                    'entity_type' => 'patrol_checkpoint_log',
                    'entity_id' => 'client-uuid-111',
                    'captured_at' => now()->subMinutes(10)->toIso8601String(),
                    'payload' => [
                        'patrol_id' => $patrolId,
                        'route_checkpoint_id' => $this->rc1->id,
                        'status' => 'scanned',
                        'scan_method' => 'qr',
                        'latitude' => 35.123456,
                        'longitude' => 33.123456,
                        'note' => 'Offline scan gate.',
                    ],
                ],
                [
                    'entity_type' => 'incident',
                    'entity_id' => 'client-uuid-222',
                    'captured_at' => now()->subMinutes(8)->toIso8601String(),
                    'payload' => [
                        'patrol_id' => $patrolId,
                        'title' => 'Broken fence observed offline',
                        'description' => 'HQ fence perimeter damage.',
                        'priority' => 'high',
                        'latitude' => 35.123500,
                        'longitude' => 33.123500,
                        'base64_media' => [
                            [
                                'filename' => 'damage.jpg',
                                'data' => base64_encode(UploadedFile::fake()->image('damage.jpg')->get()),
                            ],
                        ],
                    ],
                ],
                [
                    'entity_type' => 'guard_location_ping',
                    'entity_id' => 'client-uuid-333',
                    'captured_at' => now()->subMinutes(5)->toIso8601String(),
                    'payload' => [
                        'patrol_id' => $patrolId,
                        'latitude' => 35.123600,
                        'longitude' => 33.123600,
                        'accuracy_m' => 6.0,
                        'battery_pct' => 65,
                    ],
                ],
            ],
        ], $headers);

        $syncResponse->assertStatus(200)
            ->assertJsonPath('results.0.status', 'success')
            ->assertJsonPath('results.1.status', 'success')
            ->assertJsonPath('results.2.status', 'success');

        // Confirm database was updated correctly
        $this->assertEquals('scanned', PatrolCheckpointLog::where('route_checkpoint_id', $this->rc1->id)->first()->status);
        $patrol = Patrol::find($patrolId);
        $this->assertEquals(1, $patrol->completed_checkpoints);
        $this->assertEquals(1, $patrol->incident_count);
    }
}
