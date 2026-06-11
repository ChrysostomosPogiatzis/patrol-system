<?php

namespace Tests\Feature;

use App\Models\Location;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SuperAdminScopeTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_cannot_access_switch_tenant_endpoint(): void
    {
        $response = $this->post('/admin/api/superadmin/switch-tenant', [
            'tenant_id' => null,
        ]);

        $response->assertStatus(302);
        $response->assertRedirect(route('login'));
    }

    public function test_regular_tenant_user_cannot_access_switch_tenant_endpoint(): void
    {
        $tenant = Tenant::create([
            'name' => 'Company Tenant',
            'slug' => 'company-tenant',
        ]);

        $user = User::create([
            'tenant_id' => $tenant->id,
            'name' => 'Operator Admin',
            'email' => 'admin@company.com',
            'role' => 'admin',
            'password' => bcrypt('password'),
        ]);

        $response = $this->actingAs($user)->postJson('/admin/api/superadmin/switch-tenant', [
            'tenant_id' => $tenant->id,
        ]);

        $response->assertStatus(403);
    }

    public function test_superadmin_can_switch_context_and_filter_queries(): void
    {
        // 1. Create two tenants
        $tenantA = Tenant::create([
            'name' => 'Company A',
            'slug' => 'company-a',
        ]);

        $tenantB = Tenant::create([
            'name' => 'Company B',
            'slug' => 'company-b',
        ]);

        // 2. Create locations
        Location::create([
            'tenant_id' => $tenantA->id,
            'name' => 'Location A',
        ]);

        Location::create([
            'tenant_id' => $tenantB->id,
            'name' => 'Location B',
        ]);

        // 3. Create Super Admin (no tenant_id, role = superadmin)
        $superAdmin = User::create([
            'tenant_id' => null,
            'name' => 'Global Super Admin',
            'email' => 'superadmin@example.com',
            'role' => 'superadmin',
            'password' => bcrypt('password'),
        ]);

        // 4. Access dashboard overview as Super Admin (default: All Companies)
        $response = $this->actingAs($superAdmin)->getJson('/admin/api/overview');
        $response->assertStatus(200);
        $response->assertJsonPath('stats.locations_count', 2); // Sees both locations

        // 5. Switch context to Tenant A
        $response = $this->actingAs($superAdmin)->postJson('/admin/api/superadmin/switch-tenant', [
            'tenant_id' => $tenantA->id,
        ]);
        $response->assertStatus(200);

        // 6. Assert session override_tenant_id value
        $this->assertEquals($tenantA->id, session('override_tenant_id'));

        // 7. Access dashboard overview again (should be filtered to Tenant A)
        $response = $this->actingAs($superAdmin)->getJson('/admin/api/overview');
        $response->assertStatus(200);
        $response->assertJsonPath('stats.locations_count', 1); // Only Location A is counted now!

        // 8. Clear context
        $response = $this->actingAs($superAdmin)->postJson('/admin/api/superadmin/switch-tenant', [
            'tenant_id' => null,
        ]);
        $response->assertStatus(200);
        $this->assertFalse(session()->has('override_tenant_id'));

        // 9. Verify is back to All Companies console
        $response = $this->actingAs($superAdmin)->getJson('/admin/api/overview');
        $response->assertStatus(200);
        $response->assertJsonPath('stats.locations_count', 2); // Sees both again
    }
}
