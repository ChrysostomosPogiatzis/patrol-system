<?php

namespace Tests\Feature;

use App\Models\Checkpoint;
use App\Models\Guard;
use App\Models\Location;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SubscriptionPlanTest extends TestCase
{
    use RefreshDatabase;

    protected Tenant $tenant;
    protected User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        // Create a Basic plan tenant
        $this->tenant = Tenant::create([
            'name' => 'Basic Tenant',
            'slug' => 'basic-tenant',
            'subscription_plan' => 'basic',
        ]);

        $this->admin = User::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Company Admin',
            'email' => 'admin@basic.com',
            'role' => 'admin',
            'password' => bcrypt('password'),
        ]);
    }

    public function test_basic_plan_limits_enforced_for_guards(): void
    {
        // 1. Create two guards (should pass under Basic plan limit of 2)
        for ($i = 1; $i <= 2; $i++) {
            $response = $this->actingAs($this->admin)->postJson('/admin/api/guards', [
                'full_name' => "Guard {$i}",
                'phone' => "+3579900000{$i}",
                'employee_id' => "G-00{$i}",
                'pin' => '1234',
            ]);
            $response->assertStatus(200);
        }

        // 2. Attempt to create a third guard (should fail with 422 limit reached)
        $response = $this->actingAs($this->admin)->postJson('/admin/api/guards', [
            'full_name' => 'Guard 3',
            'phone' => '+35799000003',
            'employee_id' => 'G-003',
            'pin' => '1234',
        ]);
        $response->assertStatus(422);
        $response->assertJsonStructure(['message']);
    }

    public function test_basic_plan_limits_enforced_for_locations(): void
    {
        // 1. Create two locations (should pass under Basic plan limit of 2)
        for ($i = 1; $i <= 2; $i++) {
            $response = $this->actingAs($this->admin)->postJson('/admin/api/locations', [
                'name' => "Site {$i}",
                'latitude' => 34.000000 + $i/100,
                'longitude' => 33.000000 + $i/100,
                'geofence_radius' => 100,
            ]);
            $response->assertStatus(200);
        }

        // 2. Attempt to create a third location (should fail with 422 limit reached)
        $response = $this->actingAs($this->admin)->postJson('/admin/api/locations', [
            'name' => 'Site 3',
            'latitude' => 34.030000,
            'longitude' => 33.030000,
            'geofence_radius' => 100,
        ]);
        $response->assertStatus(422);
    }
}
