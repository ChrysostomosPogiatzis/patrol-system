<?php

namespace Tests\Feature;

use App\Models\Location;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Tests\TestCase;

class TenantScopeTest extends TestCase
{
    use RefreshDatabase;

    public function test_tenant_scoping_automatically_filters_queries_and_assigns_ids(): void
    {
        // 1. Create two tenants
        $tenantA = Tenant::create([
            'name' => 'Tenant A',
            'slug' => 'tenant-a',
        ]);

        $tenantB = Tenant::create([
            'name' => 'Tenant B',
            'slug' => 'tenant-b',
        ]);

        // 2. Bind application context to Tenant A
        app()->instance('current_tenant_id', $tenantA->id);

        // 3. Create a location (should automatically inherit tenant_id from context)
        $locationA = Location::create([
            'name' => 'Campus Alpha',
            'is_active' => true,
        ]);

        $this->assertEquals($tenantA->id, $locationA->tenant_id);

        // 4. Create a location explicitly for Tenant B (simulated background/other write)
        $locationB = Location::create([
            'tenant_id' => $tenantB->id,
            'name' => 'Warehouse Beta',
            'is_active' => true,
        ]);

        // 5. Query locations while bound to Tenant A
        $scopedLocations = Location::all();

        // 6. Assert that only Tenant A's location is fetched
        $this->assertCount(1, $scopedLocations);
        $this->assertEquals('Campus Alpha', $scopedLocations->first()->name);

        // 7. Bind application context to Tenant B
        app()->instance('current_tenant_id', $tenantB->id);

        // 8. Query locations while bound to Tenant B
        $scopedLocationsB = Location::all();

        // 9. Assert that only Tenant B's location is fetched
        $this->assertCount(1, $scopedLocationsB);
        $this->assertEquals('Warehouse Beta', $scopedLocationsB->first()->name);
    }

    public function test_tenant_scoping_via_authenticated_user(): void
    {
        // 1. Create two tenants
        $tenantA = Tenant::create([
            'name' => 'Tenant A',
            'slug' => 'tenant-a',
        ]);

        $tenantB = Tenant::create([
            'name' => 'Tenant B',
            'slug' => 'tenant-b',
        ]);

        // 2. Create users under each tenant
        $userA = User::create([
            'tenant_id' => $tenantA->id,
            'name' => 'User A',
            'email' => 'usera@example.com',
            'password' => bcrypt('password'),
        ]);

        $userB = User::create([
            'tenant_id' => $tenantB->id,
            'name' => 'User B',
            'email' => 'userb@example.com',
            'password' => bcrypt('password'),
        ]);

        // 3. Create locations explicitly for both tenants
        Location::create([
            'tenant_id' => $tenantA->id,
            'name' => 'Location A',
        ]);

        Location::create([
            'tenant_id' => $tenantB->id,
            'name' => 'Location B',
        ]);

        // 4. Authenticate as User A and query
        Auth::login($userA);
        $scopedLocationsA = Location::all();
        $this->assertCount(1, $scopedLocationsA);
        $this->assertEquals('Location A', $scopedLocationsA->first()->name);

        // 5. Authenticate as User B and query
        Auth::login($userB);
        $scopedLocationsB = Location::all();
        $this->assertCount(1, $scopedLocationsB);
        $this->assertEquals('Location B', $scopedLocationsB->first()->name);
    }

    public function test_superadmin_user_can_bypass_tenant_scoping(): void
    {
        // 1. Create two tenants
        $tenantA = Tenant::create([
            'name' => 'Tenant A',
            'slug' => 'tenant-a',
        ]);

        $tenantB = Tenant::create([
            'name' => 'Tenant B',
            'slug' => 'tenant-b',
        ]);

        // 2. Create locations for both tenants
        Location::create([
            'tenant_id' => $tenantA->id,
            'name' => 'Location A',
        ]);

        Location::create([
            'tenant_id' => $tenantB->id,
            'name' => 'Location B',
        ]);

        // 3. Create Super Admin User (tenant_id = null, role = superadmin)
        $superAdmin = User::create([
            'tenant_id' => null,
            'name' => 'Super Admin',
            'email' => 'superadmin@example.com',
            'role' => 'superadmin',
            'password' => bcrypt('password'),
        ]);

        // 4. Authenticate as Super Admin and query
        Auth::login($superAdmin);

        // Should return locations from both Tenant A and Tenant B
        $locations = Location::all();
        $this->assertCount(2, $locations);
    }
}
