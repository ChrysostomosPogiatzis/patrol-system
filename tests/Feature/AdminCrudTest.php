<?php

namespace Tests\Feature;

use App\Models\Checkpoint;
use App\Models\Guard;
use App\Models\Location;
use App\Models\PatrolContact;
use App\Models\Route;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminCrudTest extends TestCase
{
    use RefreshDatabase;

    protected Tenant $tenantA;
    protected Tenant $tenantB;
    protected User $userA;
    protected User $userB;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenantA = Tenant::create(['name' => 'Company A', 'slug' => 'company-a']);
        $this->tenantB = Tenant::create(['name' => 'Company B', 'slug' => 'company-b']);

        $this->userA = User::create([
            'tenant_id' => $this->tenantA->id,
            'name' => 'Admin A',
            'email' => 'adminA@test.com',
            'role' => 'admin',
            'password' => bcrypt('password'),
        ]);

        $this->userB = User::create([
            'tenant_id' => $this->tenantB->id,
            'name' => 'Admin B',
            'email' => 'adminB@test.com',
            'role' => 'admin',
            'password' => bcrypt('password'),
        ]);
    }

    public function test_can_update_and_delete_guard_within_tenant(): void
    {
        $guard = Guard::create([
            'tenant_id' => $this->tenantA->id,
            'full_name' => 'Guard A',
            'phone' => '+35799111222',
            'employee_id' => 'G-111',
            'pin' => bcrypt('1234'),
            'is_active' => true,
        ]);

        // 1. Update Guard A details
        $response = $this->actingAs($this->userA)->putJson("/admin/api/guards/{$guard->id}", [
            'full_name' => 'Guard A Edited',
            'phone' => '+35799111223',
            'employee_id' => 'G-111-ED',
            'pin' => '5555',
        ]);
        $response->assertStatus(200);
        $this->assertDatabaseHas('guards', [
            'id' => $guard->id,
            'full_name' => 'Guard A Edited',
            'phone' => '+35799111223',
            'employee_id' => 'G-111-ED',
        ]);

        // 2. Prevent User B from editing/deleting Guard A
        $response = $this->actingAs($this->userB)->putJson("/admin/api/guards/{$guard->id}", [
            'full_name' => 'Hacked Name',
            'phone' => '+35799111223',
            'employee_id' => 'G-111-ED',
        ]);
        $response->assertStatus(404);

        $response = $this->actingAs($this->userB)->deleteJson("/admin/api/guards/{$guard->id}");
        $response->assertStatus(404);

        // 3. User A deletes Guard A
        $response = $this->actingAs($this->userA)->deleteJson("/admin/api/guards/{$guard->id}");
        $response->assertStatus(200);
        $this->assertDatabaseMissing('guards', ['id' => $guard->id]);
    }

    public function test_can_update_and_delete_location_and_checkpoint(): void
    {
        $location = Location::create([
            'tenant_id' => $this->tenantA->id,
            'name' => 'Old Site',
            'latitude' => 34.123,
            'longitude' => 33.456,
            'geofence_radius' => 100,
        ]);

        $checkpoint = Checkpoint::create([
            'tenant_id' => $this->tenantA->id,
            'location_id' => $location->id,
            'name' => 'Old CP',
            'scan_method' => 'qr',
            'qr_code' => 'QR-OLD',
            'gps_required' => true,
            'gps_fence_radius' => 10,
            'photo_requirement' => 'off',
            'note_requirement' => 'off',
            'voice_requirement' => 'off',
            'signature_required' => false,
            'incident_enabled' => true,
        ]);

        // 1. Update Site & Checkpoint details
        $response = $this->actingAs($this->userA)->putJson("/admin/api/locations/{$location->id}", [
            'name' => 'New Site Name',
            'latitude' => 34.888,
            'longitude' => 33.999,
            'geofence_radius' => 150,
        ]);
        $response->assertStatus(200);
        $this->assertDatabaseHas('locations', ['name' => 'New Site Name']);

        $response = $this->actingAs($this->userA)->putJson("/admin/api/checkpoints/{$checkpoint->id}", [
            'location_id' => $location->id,
            'name' => 'New CP Name',
            'scan_method' => 'both',
            'qr_code' => 'QR-NEW',
            'nfc_tag_id' => 'NFC-NEW',
            'gps_required' => false,
            'gps_fence_radius' => 20,
            'photo_requirement' => 'required',
            'note_requirement' => 'optional',
            'voice_requirement' => 'off',
            'signature_required' => true,
        ]);
        $response->assertStatus(200);
        $this->assertDatabaseHas('checkpoints', ['name' => 'New CP Name', 'scan_method' => 'both']);

        // 2. Prevent User B from deleting Location or Checkpoint
        $response = $this->actingAs($this->userB)->deleteJson("/admin/api/checkpoints/{$checkpoint->id}");
        $response->assertStatus(404);

        // 3. User A deletes Checkpoint and Location
        $response = $this->actingAs($this->userA)->deleteJson("/admin/api/checkpoints/{$checkpoint->id}");
        $response->assertStatus(200);

        $response = $this->actingAs($this->userA)->deleteJson("/admin/api/locations/{$location->id}");
        $response->assertStatus(200);

        $this->assertDatabaseMissing('checkpoints', ['id' => $checkpoint->id]);
        $this->assertDatabaseMissing('locations', ['id' => $location->id]);
    }

    public function test_can_update_and_delete_patrol_route(): void
    {
        $location = Location::create([
            'tenant_id' => $this->tenantA->id,
            'name' => 'Test Site',
            'latitude' => 34.1,
            'longitude' => 33.1,
            'geofence_radius' => 100,
        ]);

        $cp1 = Checkpoint::create([
            'tenant_id' => $this->tenantA->id,
            'location_id' => $location->id,
            'name' => 'CP1',
            'scan_method' => 'qr',
            'gps_required' => false,
            'gps_fence_radius' => 10,
            'photo_requirement' => 'off',
            'note_requirement' => 'off',
            'voice_requirement' => 'off',
            'signature_required' => false,
            'incident_enabled' => true,
        ]);

        $route = Route::create([
            'tenant_id' => $this->tenantA->id,
            'name' => 'Old Route',
            'enforce_order' => true,
            'allow_skip' => false,
            'expected_duration_mins' => 20,
        ]);

        // Update Route
        $response = $this->actingAs($this->userA)->putJson("/admin/api/routes/{$route->id}", [
            'name' => 'Updated Route Name',
            'enforce_order' => false,
            'allow_skip' => true,
            'expected_duration_mins' => 45,
            'checkpoints' => [$cp1->id],
        ]);
        $response->assertStatus(200);
        $this->assertDatabaseHas('routes', ['name' => 'Updated Route Name', 'allow_skip' => true]);

        // Delete Route
        $response = $this->actingAs($this->userA)->deleteJson("/admin/api/routes/{$route->id}");
        $response->assertStatus(200);
        $this->assertDatabaseMissing('routes', ['id' => $route->id]);
    }

    public function test_can_update_and_delete_alert_contact(): void
    {
        $contact = PatrolContact::create([
            'tenant_id' => $this->tenantA->id,
            'name' => 'Old Contact',
            'role_label' => 'Manager',
            'phone' => '+357000',
            'email' => 'old@contact.com',
            'notify_channels' => ['email'],
            'notify_on' => ['sos_triggered'],
            'is_active' => true,
        ]);

        // Update
        $response = $this->actingAs($this->userA)->putJson("/admin/api/contacts/{$contact->id}", [
            'name' => 'New Contact Name',
            'role_label' => 'Director',
            'phone' => '+357999',
            'email' => 'new@contact.com',
            'notify_channels' => ['sms', 'email'],
            'notify_on' => ['patrol_completed'],
        ]);
        $response->assertStatus(200);
        $this->assertDatabaseHas('patrol_contacts', ['name' => 'New Contact Name', 'role_label' => 'Director']);

        // Delete
        $response = $this->actingAs($this->userA)->deleteJson("/admin/api/contacts/{$contact->id}");
        $response->assertStatus(200);
        $this->assertDatabaseMissing('patrol_contacts', ['id' => $contact->id]);
    }
}
