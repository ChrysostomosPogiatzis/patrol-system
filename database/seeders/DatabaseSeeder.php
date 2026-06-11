<?php

namespace Database\Seeders;

use App\Models\Tenant;
use App\Models\User;
use App\Models\Guard;
use App\Models\Location;
use App\Models\Checkpoint;
use App\Models\Route;
use App\Models\RouteCheckpoint;
use App\Models\RouteAssignment;
use App\Models\PatrolContact;
use App\Models\Patrol;
use App\Models\Incident;
use App\Models\SosAlert;
use App\Models\GuardLocationPing;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // ==========================================
        // GLOBAL SEEDING: SUPER ADMIN
        // ==========================================
        User::create([
            'tenant_id' => null,
            'name' => 'Sentinel Super Admin',
            'email' => 'superadmin@sentinel.com',
            'phone' => '+35799000000',
            'role' => 'superadmin',
            'password' => bcrypt('password'),
            'is_active' => true,
        ]);

        // ==========================================
        // TENANT 1: Sentinel Security Ltd
        // ==========================================
        $tenant1 = Tenant::create([
            'name' => 'Sentinel Security Ltd',
            'slug' => 'sentinel',
            'phone' => '+35725123456',
            'email' => 'info@sentinelsecurity.com',
            'address' => 'Marina Road 10, Limassol, Cyprus',
            'timezone' => 'Asia/Nicosia',
            'subscription_plan' => 'enterprise',
            'is_active' => true,
        ]);

        // Admin User
        User::create([
            'tenant_id' => $tenant1->id,
            'name' => 'Sentinel Admin',
            'email' => 'admin@sentinel.com',
            'phone' => '+35799000001',
            'role' => 'admin',
            'password' => bcrypt('password'),
            'is_active' => true,
        ]);

        // Guards
        $guardT1_1 = Guard::create([
            'tenant_id' => $tenant1->id,
            'full_name' => 'John Doe',
            'phone' => '+35799123456',
            'employee_id' => 'GD-007',
            'pin' => bcrypt('1234'),
            'is_active' => true,
        ]);

        $guardT1_2 = Guard::create([
            'tenant_id' => $tenant1->id,
            'full_name' => 'Jane Smith',
            'phone' => '+35799654321',
            'employee_id' => 'GD-008',
            'pin' => bcrypt('4321'),
            'is_active' => true,
        ]);

        // Location
        $locT1 = Location::create([
            'tenant_id' => $tenant1->id,
            'name' => 'Limassol Marina Port Office',
            'address' => 'Marina Street 12',
            'city' => 'Limassol',
            'country' => 'Cyprus',
            'latitude' => 34.671234,
            'longitude' => 33.041234,
            'geofence_radius' => 200,
            'timezone' => 'Asia/Nicosia',
            'is_active' => true,
        ]);

        // Checkpoints
        $cpT1_1 = Checkpoint::create([
            'tenant_id' => $tenant1->id,
            'location_id' => $locT1->id,
            'name' => 'Main Gate Entrance',
            'description' => 'Verify access gate is locked and perimeter is secure.',
            'scan_method' => 'qr',
            'qr_code' => 'PORT-GATE-01',
            'gps_required' => true,
            'gps_fence_radius' => 20,
            'latitude' => 34.671200,
            'longitude' => 33.041200,
            'photo_requirement' => 'optional',
            'note_requirement' => 'optional',
            'voice_requirement' => 'off',
            'signature_required' => false,
            'is_active' => true,
        ]);

        $cpT1_2 = Checkpoint::create([
            'tenant_id' => $tenant1->id,
            'location_id' => $locT1->id,
            'name' => 'Control Center Room',
            'description' => 'Check server racks temperature and backup generators state.',
            'scan_method' => 'both',
            'qr_code' => 'PORT-CTRL-02',
            'nfc_tag_id' => 'NFC-CTRL-02',
            'gps_required' => true,
            'gps_fence_radius' => 15,
            'latitude' => 34.671300,
            'longitude' => 33.041300,
            'photo_requirement' => 'required',
            'note_requirement' => 'required',
            'voice_requirement' => 'optional',
            'signature_required' => true,
            'is_active' => true,
        ]);

        $cpT1_3 = Checkpoint::create([
            'tenant_id' => $tenant1->id,
            'location_id' => $locT1->id,
            'name' => 'West Fuel Dock Pier',
            'description' => 'Inspect docking lines and ensure no diesel leakage is visible.',
            'scan_method' => 'nfc',
            'nfc_tag_id' => 'NFC-FUEL-03',
            'gps_required' => true,
            'gps_fence_radius' => 25,
            'latitude' => 34.671400,
            'longitude' => 33.041400,
            'photo_requirement' => 'off',
            'note_requirement' => 'off',
            'voice_requirement' => 'off',
            'signature_required' => false,
            'is_active' => true,
        ]);

        // Route
        $routeT1 = Route::create([
            'tenant_id' => $tenant1->id,
            'name' => 'Port Docking Night Patrol',
            'description' => 'Regular night patrol covering entrance gates, controls room, and West Fuel Dock.',
            'enforce_order' => true,
            'allow_skip' => true,
            'expected_duration_mins' => 45,
            'is_active' => true,
        ]);

        RouteCheckpoint::create([
            'route_id' => $routeT1->id,
            'checkpoint_id' => $cpT1_1->id,
            'position' => 1,
        ]);

        RouteCheckpoint::create([
            'route_id' => $routeT1->id,
            'checkpoint_id' => $cpT1_2->id,
            'position' => 2,
        ]);

        RouteCheckpoint::create([
            'route_id' => $routeT1->id,
            'checkpoint_id' => $cpT1_3->id,
            'position' => 3,
        ]);

        // Route Assignment
        RouteAssignment::create([
            'tenant_id' => $tenant1->id,
            'route_id' => $routeT1->id,
            'guard_id' => $guardT1_1->id,
            'schedule_start' => now()->subDays(1),
            'schedule_end' => now()->addDays(30),
            'is_active' => true,
        ]);

        // Live Active Patrol
        $patrolT1 = Patrol::create([
            'tenant_id' => $tenant1->id,
            'route_id' => $routeT1->id,
            'guard_id' => $guardT1_1->id,
            'status' => 'in_progress',
            'started_at' => now()->subMinutes(12),
            'total_checkpoints' => 3,
            'completed_checkpoints' => 1,
        ]);

        // Open Incident
        Incident::create([
            'tenant_id' => $tenant1->id,
            'patrol_id' => $patrolT1->id,
            'checkpoint_id' => $cpT1_3->id,
            'location_id' => $locT1->id,
            'guard_id' => $guardT1_1->id,
            'title' => 'Suspicious Vessel Near Dock',
            'description' => 'Unidentified motorboat observed loitering near the West Fuel Dock Pier without navigation lights.',
            'priority' => 'high',
            'status' => 'open',
            'incident_latitude' => 34.671400,
            'incident_longitude' => 33.041400,
        ]);

        // Guard Location Pings
        GuardLocationPing::create([
            'tenant_id' => $tenant1->id,
            'guard_id' => $guardT1_1->id,
            'patrol_id' => $patrolT1->id,
            'latitude' => 34.671350,
            'longitude' => 33.041250,
            'accuracy_m' => 4.20,
            'battery_pct' => 84,
            'is_online' => true,
            'pinged_at' => now(),
        ]);

        GuardLocationPing::create([
            'tenant_id' => $tenant1->id,
            'guard_id' => $guardT1_2->id,
            'patrol_id' => null,
            'latitude' => 34.671580,
            'longitude' => 33.041950,
            'accuracy_m' => 6.50,
            'battery_pct' => 97,
            'is_online' => true,
            'pinged_at' => now()->subMinutes(3),
        ]);

        // Triggered Active SOS Alarm
        SosAlert::create([
            'tenant_id' => $tenant1->id,
            'guard_id' => $guardT1_2->id,
            'patrol_id' => null,
            'status' => 'active',
            'triggered_latitude' => 34.671580,
            'triggered_longitude' => 33.041950,
            'note' => 'Guard triggered emergency distress alert via app panic button.',
            'triggered_at' => now()->subMinutes(3),
        ]);

        // Patrol Contact
        PatrolContact::create([
            'tenant_id' => $tenant1->id,
            'name' => 'Ops Director',
            'role_label' => 'Security Director',
            'phone' => '+35799887766',
            'email' => 'ops@sentinelsecurity.com',
            'notify_channels' => ['email', 'sms'],
            'notify_on' => ['patrol_completed', 'incident_created', 'sos_triggered'],
            'is_active' => true,
        ]);


        // ==========================================
        // TENANT 2: Centurion Security Services
        // ==========================================
        $tenant2 = Tenant::create([
            'name' => 'Centurion Security Services',
            'slug' => 'centurion',
            'phone' => '+35724998877',
            'email' => 'info@centurionguards.com',
            'address' => 'Vasileos Georgiou 42, Larnaca, Cyprus',
            'timezone' => 'Asia/Nicosia',
            'subscription_plan' => 'standard',
            'is_active' => true,
        ]);

        // Admin User
        User::create([
            'tenant_id' => $tenant2->id,
            'name' => 'Centurion Admin',
            'email' => 'admin@centurion.com',
            'phone' => '+35799000002',
            'role' => 'admin',
            'password' => bcrypt('password'),
            'is_active' => true,
        ]);

        // Guards
        $guardT2_1 = Guard::create([
            'tenant_id' => $tenant2->id,
            'full_name' => 'Marcus Aurelius',
            'phone' => '+35799001122',
            'employee_id' => 'C-201',
            'pin' => bcrypt('9999'),
            'is_active' => true,
        ]);

        $guardT2_2 = Guard::create([
            'tenant_id' => $tenant2->id,
            'full_name' => 'Lucius Vorenus',
            'phone' => '+35799334455',
            'employee_id' => 'C-202',
            'pin' => bcrypt('8888'),
            'is_active' => true,
        ]);

        // Location
        $locT2 = Location::create([
            'tenant_id' => $tenant2->id,
            'name' => 'Larnaca Industrial Warehouse',
            'address' => 'Industrial Zone Block B',
            'city' => 'Larnaca',
            'country' => 'Cyprus',
            'latitude' => 34.917234,
            'longitude' => 33.621234,
            'geofence_radius' => 300,
            'timezone' => 'Asia/Nicosia',
            'is_active' => true,
        ]);

        // Checkpoints
        $cpT2_1 = Checkpoint::create([
            'tenant_id' => $tenant2->id,
            'location_id' => $locT2->id,
            'name' => 'North Perimeter Fence',
            'description' => 'Inspect outer chain link boundary for breaches or cuts.',
            'scan_method' => 'qr',
            'qr_code' => 'CENT-PERI-01',
            'gps_required' => true,
            'gps_fence_radius' => 20,
            'latitude' => 34.917100,
            'longitude' => 33.621100,
            'photo_requirement' => 'optional',
            'note_requirement' => 'off',
            'voice_requirement' => 'off',
            'signature_required' => false,
            'is_active' => true,
        ]);

        $cpT2_2 = Checkpoint::create([
            'tenant_id' => $tenant2->id,
            'location_id' => $locT2->id,
            'name' => 'Loading Dock Beta',
            'description' => 'Verify dock shutter is fully retracted and lock holds.',
            'scan_method' => 'nfc',
            'nfc_tag_id' => 'CENT-NFC-DOCK',
            'gps_required' => true,
            'gps_fence_radius' => 15,
            'latitude' => 34.917250,
            'longitude' => 33.621280,
            'photo_requirement' => 'required',
            'note_requirement' => 'optional',
            'voice_requirement' => 'off',
            'signature_required' => true,
            'is_active' => true,
        ]);

        $cpT2_3 = Checkpoint::create([
            'tenant_id' => $tenant2->id,
            'location_id' => $locT2->id,
            'name' => 'Main Storage Vault',
            'description' => 'Check keycard door sensor and verify vault warning lock LEDs are green.',
            'scan_method' => 'both',
            'qr_code' => 'CENT-QR-VAULT',
            'nfc_tag_id' => 'CENT-NFC-VAULT',
            'gps_required' => false,
            'gps_fence_radius' => 30,
            'latitude' => 34.917400,
            'longitude' => 33.621450,
            'photo_requirement' => 'off',
            'note_requirement' => 'required',
            'voice_requirement' => 'required',
            'signature_required' => true,
            'is_active' => true,
        ]);

        // Route
        $routeT2 = Route::create([
            'tenant_id' => $tenant2->id,
            'name' => 'Larnaca Warehouse Secure Check',
            'description' => 'Comprehensive evening check including storage vault and loading docks.',
            'enforce_order' => true,
            'allow_skip' => false,
            'expected_duration_mins' => 30,
            'is_active' => true,
        ]);

        RouteCheckpoint::create([
            'route_id' => $routeT2->id,
            'checkpoint_id' => $cpT2_1->id,
            'position' => 1,
        ]);

        RouteCheckpoint::create([
            'route_id' => $routeT2->id,
            'checkpoint_id' => $cpT2_2->id,
            'position' => 2,
        ]);

        RouteCheckpoint::create([
            'route_id' => $routeT2->id,
            'checkpoint_id' => $cpT2_3->id,
            'position' => 3,
        ]);

        // Route Assignment
        RouteAssignment::create([
            'tenant_id' => $tenant2->id,
            'route_id' => $routeT2->id,
            'guard_id' => $guardT2_1->id,
            'schedule_start' => now()->subDays(1),
            'schedule_end' => now()->addDays(30),
            'is_active' => true,
        ]);

        // Live Active Patrol
        $patrolT2 = Patrol::create([
            'tenant_id' => $tenant2->id,
            'route_id' => $routeT2->id,
            'guard_id' => $guardT2_1->id,
            'status' => 'in_progress',
            'started_at' => now()->subMinutes(25),
            'total_checkpoints' => 3,
            'completed_checkpoints' => 2,
        ]);

        // Open Incident
        Incident::create([
            'tenant_id' => $tenant2->id,
            'patrol_id' => $patrolT2->id,
            'checkpoint_id' => $cpT2_1->id,
            'location_id' => $locT2->id,
            'guard_id' => $guardT2_1->id,
            'title' => 'Outer Perimeter Fence Cut',
            'description' => 'Found a 1.5-meter vertical slice in the outer chain link fence near north gate. No signs of entry.',
            'priority' => 'critical',
            'status' => 'open',
            'incident_latitude' => 34.917100,
            'incident_longitude' => 33.621100,
        ]);

        // Guard Location Pings
        GuardLocationPing::create([
            'tenant_id' => $tenant2->id,
            'guard_id' => $guardT2_1->id,
            'patrol_id' => $patrolT2->id,
            'latitude' => 34.917220,
            'longitude' => 33.621250,
            'accuracy_m' => 3.80,
            'battery_pct' => 42,
            'is_online' => true,
            'pinged_at' => now(),
        ]);

        GuardLocationPing::create([
            'tenant_id' => $tenant2->id,
            'guard_id' => $guardT2_2->id,
            'patrol_id' => null,
            'latitude' => 34.918110,
            'longitude' => 33.622150,
            'accuracy_m' => 9.20,
            'battery_pct' => 68,
            'is_online' => false,
            'pinged_at' => now()->subMinutes(18),
        ]);

        // Patrol Contact
        PatrolContact::create([
            'tenant_id' => $tenant2->id,
            'name' => 'Centurion Dispatcher',
            'role_label' => 'Lead Dispatcher',
            'phone' => '+35799776655',
            'email' => 'dispatch@centurionguards.com',
            'notify_channels' => ['email'],
            'notify_on' => ['incident_created', 'sos_triggered'],
            'is_active' => true,
        ]);
    }
}
