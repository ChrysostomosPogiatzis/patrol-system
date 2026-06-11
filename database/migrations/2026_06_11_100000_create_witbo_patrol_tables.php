<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // 1. Locations
        Schema::create('locations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->onDelete('cascade');
            $table->string('name');
            $table->string('address')->nullable();
            $table->string('city')->nullable();
            $table->string('country')->nullable();
            $table->decimal('latitude', 10, 8)->nullable();
            $table->decimal('longitude', 11, 8)->nullable();
            $table->integer('geofence_radius')->default(500); // meters
            $table->string('timezone')->nullable();
            $table->boolean('is_active')->default(true);
            $table->json('meta')->nullable();
            $table->timestamps();
        });

        // 2. Checkpoints
        Schema::create('checkpoints', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->onDelete('cascade');
            $table->foreignId('location_id')->constrained('locations')->onDelete('cascade');
            $table->string('name');
            $table->text('description')->nullable();
            $table->string('scan_method')->default('qr'); // qr, nfc, both
            $table->string('qr_code')->nullable();
            $table->string('nfc_tag_id')->nullable();
            $table->boolean('gps_required')->default(true);
            $table->integer('gps_fence_radius')->default(15); // meters
            $table->decimal('latitude', 10, 8)->nullable();
            $table->decimal('longitude', 11, 8)->nullable();
            $table->string('photo_requirement')->default('off'); // off, optional, required
            $table->string('note_requirement')->default('off'); // off, optional, required
            $table->string('voice_requirement')->default('off'); // off, optional, required
            $table->boolean('signature_required')->default(false);
            $table->boolean('incident_enabled')->default(true);
            $table->boolean('is_active')->default(true);
            $table->json('meta')->nullable();
            $table->timestamps();
        });

        // 3. Routes
        Schema::create('routes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->onDelete('cascade');
            $table->string('name');
            $table->text('description')->nullable();
            $table->boolean('enforce_order')->default(true);
            $table->boolean('allow_skip')->default(false);
            $table->integer('expected_duration_mins')->nullable();
            $table->boolean('is_active')->default(true);
            $table->foreignId('created_by')->nullable()->constrained('users')->onDelete('set null');
            $table->timestamps();
        });

        // 4. Route Checkpoints (Ordered sequence)
        Schema::create('route_checkpoints', function (Blueprint $table) {
            $table->id();
            $table->foreignId('route_id')->constrained('routes')->onDelete('cascade');
            $table->foreignId('checkpoint_id')->constrained('checkpoints')->onDelete('cascade');
            $table->integer('position'); // 1-based ordering sequence
            $table->string('photo_requirement')->nullable(); // overrides checkpoint if set
            $table->string('note_requirement')->nullable();
            $table->string('voice_requirement')->nullable();
            $table->boolean('signature_required')->nullable();
            $table->timestamps();

            $table->unique(['route_id', 'checkpoint_id']);
            $table->unique(['route_id', 'position']);
        });

        // 5. Guards
        Schema::create('guards', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->onDelete('cascade');
            $table->string('full_name');
            $table->string('phone');
            $table->string('employee_id')->nullable();
            $table->string('avatar_url')->nullable();
            $table->string('pin')->nullable(); // offline mode fallback PIN hash
            $table->boolean('is_active')->default(true);
            $table->timestamp('last_login_at')->nullable();
            $table->timestamp('last_seen_at')->nullable();
            $table->timestamps();

            $table->unique(['tenant_id', 'phone']);
        });

        // 6. Guard OTP tokens
        Schema::create('guard_otp_tokens', function (Blueprint $table) {
            $table->id();
            $table->foreignId('guard_id')->constrained('guards')->onDelete('cascade');
            $table->string('token_hash');
            $table->timestamp('expires_at');
            $table->boolean('used')->default(false);
            $table->timestamps();
        });

        // 7. Route Assignments
        Schema::create('route_assignments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->onDelete('cascade');
            $table->foreignId('route_id')->constrained('routes')->onDelete('cascade');
            $table->foreignId('guard_id')->constrained('guards')->onDelete('cascade');
            $table->timestamp('schedule_start')->nullable();
            $table->timestamp('schedule_end')->nullable();
            $table->string('cron_schedule')->nullable();
            $table->boolean('is_active')->default(true);
            $table->foreignId('assigned_by')->nullable()->constrained('users')->onDelete('set null');
            $table->timestamps();

            $table->unique(['route_id', 'guard_id']);
        });

        // 8. Patrols (Patrol sessions)
        Schema::create('patrols', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->onDelete('cascade');
            $table->foreignId('route_id')->constrained('routes')->onDelete('restrict');
            $table->foreignId('guard_id')->constrained('guards')->onDelete('restrict');
            $table->string('status')->default('pending'); // pending, in_progress, completed, abandoned
            $table->timestamp('scheduled_start')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->integer('duration_seconds')->nullable();
            $table->text('general_note')->nullable();
            $table->boolean('completed_by_guard')->default(false);
            $table->decimal('completion_latitude', 10, 8)->nullable();
            $table->decimal('completion_longitude', 11, 8)->nullable();
            $table->string('completion_signature_url')->nullable();
            $table->boolean('was_offline')->default(false);
            $table->timestamp('synced_at')->nullable();
            $table->integer('total_checkpoints')->default(0);
            $table->integer('completed_checkpoints')->default(0);
            $table->integer('skipped_checkpoints')->default(0);
            $table->integer('incident_count')->default(0);
            $table->timestamps();
        });

        // 9. Patrol Checkpoint Logs (Scans / actions per checkpoint in a session)
        Schema::create('patrol_checkpoint_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->onDelete('cascade');
            $table->foreignId('patrol_id')->constrained('patrols')->onDelete('cascade');
            $table->foreignId('route_checkpoint_id')->constrained('route_checkpoints')->onDelete('restrict');
            $table->foreignId('checkpoint_id')->constrained('checkpoints')->onDelete('restrict');
            $table->foreignId('guard_id')->constrained('guards')->onDelete('restrict');
            $table->string('status')->default('pending'); // pending, scanned, skipped, out_of_order_attempt
            $table->integer('position');
            $table->string('scan_method_used')->nullable(); // qr, nfc
            $table->timestamp('scanned_at')->nullable();
            $table->decimal('scan_latitude', 10, 8)->nullable();
            $table->decimal('scan_longitude', 11, 8)->nullable();
            $table->decimal('gps_distance_metres', 8, 2)->nullable();
            $table->boolean('gps_within_fence')->nullable();
            $table->text('note')->nullable();
            $table->text('skip_reason')->nullable();
            $table->timestamp('skipped_at')->nullable();
            $table->integer('attempted_position')->nullable();
            $table->boolean('recorded_offline')->default(false);
            $table->timestamp('device_timestamp')->nullable();
            $table->timestamp('synced_at')->nullable();
            $table->timestamps();
        });

        // 10. Checkpoint Media (Photos, voice recordings attached to checkpoint logs)
        Schema::create('checkpoint_media', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->onDelete('cascade');
            $table->foreignId('patrol_checkpoint_log_id')->constrained('patrol_checkpoint_logs')->onDelete('cascade');
            $table->foreignId('patrol_id')->constrained('patrols')->onDelete('cascade');
            $table->foreignId('guard_id')->constrained('guards')->onDelete('restrict');
            $table->string('kind'); // photo, voice_memo, signature
            $table->string('file_url');
            $table->string('file_key');
            $table->integer('file_size_bytes')->nullable();
            $table->string('mime_type')->nullable();
            $table->integer('duration_seconds')->nullable(); // for voice memo
            $table->timestamp('captured_at');
            $table->decimal('capture_latitude', 10, 8)->nullable();
            $table->decimal('capture_longitude', 11, 8)->nullable();
            $table->boolean('recorded_offline')->default(false);
            $table->timestamp('synced_at')->nullable();
            $table->timestamps();
        });

        // 11. Incidents
        Schema::create('incidents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->onDelete('cascade');
            $table->foreignId('patrol_id')->nullable()->constrained('patrols')->onDelete('set null');
            $table->foreignId('patrol_checkpoint_log_id')->nullable()->constrained('patrol_checkpoint_logs')->onDelete('set null');
            $table->foreignId('checkpoint_id')->nullable()->constrained('checkpoints')->onDelete('set null');
            $table->foreignId('location_id')->nullable()->constrained('locations')->onDelete('set null');
            $table->foreignId('guard_id')->constrained('guards')->onDelete('restrict');
            $table->string('title');
            $table->text('description')->nullable();
            $table->string('priority')->default('medium'); // low, medium, high, critical
            $table->string('status')->default('open'); // open, under_review, resolved, closed
            $table->decimal('incident_latitude', 10, 8)->nullable();
            $table->decimal('incident_longitude', 11, 8)->nullable();
            $table->foreignId('resolved_by')->nullable()->constrained('users')->onDelete('set null');
            $table->timestamp('resolved_at')->nullable();
            $table->text('resolution_note')->nullable();
            $table->boolean('recorded_offline')->default(false);
            $table->timestamp('device_timestamp')->nullable();
            $table->timestamp('synced_at')->nullable();
            $table->timestamps();
        });

        // 12. Incident Media
        Schema::create('incident_media', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->onDelete('cascade');
            $table->foreignId('incident_id')->constrained('incidents')->onDelete('cascade');
            $table->foreignId('guard_id')->constrained('guards')->onDelete('restrict');
            $table->string('kind'); // photo, voice_memo
            $table->string('file_url');
            $table->string('file_key');
            $table->integer('file_size_bytes')->nullable();
            $table->string('mime_type')->nullable();
            $table->integer('duration_seconds')->nullable();
            $table->timestamp('captured_at');
            $table->decimal('capture_latitude', 10, 8)->nullable();
            $table->decimal('capture_longitude', 11, 8)->nullable();
            $table->boolean('recorded_offline')->default(false);
            $table->timestamp('synced_at')->nullable();
            $table->timestamps();
        });

        // 13. SOS Alerts (Emergencies)
        Schema::create('sos_alerts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->onDelete('cascade');
            $table->foreignId('guard_id')->constrained('guards')->onDelete('cascade');
            $table->foreignId('patrol_id')->nullable()->constrained('patrols')->onDelete('set null');
            $table->string('status')->default('active'); // active, acknowledged, resolved, false_alarm
            $table->decimal('triggered_latitude', 10, 8)->nullable();
            $table->decimal('triggered_longitude', 11, 8)->nullable();
            $table->text('note')->nullable();
            $table->foreignId('acknowledged_by')->nullable()->constrained('users')->onDelete('set null');
            $table->timestamp('acknowledged_at')->nullable();
            $table->foreignId('resolved_by')->nullable()->constrained('users')->onDelete('set null');
            $table->timestamp('resolved_at')->nullable();
            $table->text('resolution_note')->nullable();
            $table->timestamp('triggered_at')->useCurrent();
            $table->timestamps();
        });

        // 14. SOS Location Pings (Frequent tracking while SOS is active)
        Schema::create('sos_location_pings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sos_alert_id')->constrained('sos_alerts')->onDelete('cascade');
            $table->foreignId('guard_id')->constrained('guards')->onDelete('cascade');
            $table->decimal('latitude', 10, 8);
            $table->decimal('longitude', 11, 8);
            $table->decimal('accuracy_m', 8, 2)->nullable();
            $table->timestamp('pinged_at')->useCurrent();
        });

        // 15. Guard Location Pings (Normal tracking)
        Schema::create('guard_location_pings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->onDelete('cascade');
            $table->foreignId('guard_id')->constrained('guards')->onDelete('cascade');
            $table->foreignId('patrol_id')->nullable()->constrained('patrols')->onDelete('set null');
            $table->decimal('latitude', 10, 8);
            $table->decimal('longitude', 11, 8);
            $table->decimal('accuracy_m', 8, 2)->nullable();
            $table->smallInteger('battery_pct')->nullable();
            $table->boolean('is_online')->default(true);
            $table->boolean('recorded_offline')->default(false);
            $table->timestamp('device_timestamp')->nullable();
            $table->timestamp('pinged_at')->useCurrent();
        });

        // 16. Offline Sync Queue
        Schema::create('offline_sync_queue', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->onDelete('cascade');
            $table->foreignId('guard_id')->constrained('guards')->onDelete('cascade');
            $table->foreignId('patrol_id')->nullable()->constrained('patrols')->onDelete('set null');
            $table->string('entity_type'); // e.g. patrol_checkpoint_log, incident, media
            $table->uuid('entity_id')->nullable(); // local client UUID
            $table->json('payload');
            $table->string('state')->default('queued'); // queued, syncing, synced, failed
            $table->smallInteger('retry_count')->default(0);
            $table->text('last_error')->nullable();
            $table->timestamp('captured_at');
            $table->timestamp('queued_at')->useCurrent();
            $table->timestamp('synced_at')->nullable();
        });

        // 17. Patrol Contacts (Who gets SMS/email alerts)
        Schema::create('patrol_contacts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->onDelete('cascade');
            $table->string('name');
            $table->string('role_label')->nullable(); // Security Owner, Supervisor, Client, etc.
            $table->string('phone')->nullable();
            $table->string('email')->nullable();
            $table->json('notify_channels'); // Array: ['email', 'sms', 'push']
            $table->json('notify_on'); // Array: ['patrol_completed', 'incident_created', 'sos_triggered']
            $table->json('route_ids')->nullable(); // Array of allowed route IDs or null for all
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        // 18. Notification Log
        Schema::create('notification_log', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->onDelete('cascade');
            $table->foreignId('contact_id')->nullable()->constrained('patrol_contacts')->onDelete('set null');
            $table->foreignId('guard_id')->nullable()->constrained('guards')->onDelete('set null');
            $table->string('channel'); // sms, email, push
            $table->string('trigger'); // sos_triggered, patrol_completed, etc.
            $table->string('entity_type')->nullable(); // patrol, incident, sos_alert
            $table->foreignId('entity_id')->nullable();
            $table->string('recipient');
            $table->string('subject')->nullable();
            $table->text('body');
            $table->boolean('sent')->default(false);
            $table->timestamp('sent_at')->nullable();
            $table->text('error')->nullable();
            $table->timestamps();
        });

        // 19. Audit Logs
        Schema::create('audit_log', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->onDelete('cascade');
            $table->string('actor_type'); // user, guard, system
            $table->foreignId('actor_id')->nullable();
            $table->string('action'); // e.g. route.created, checkpoint.scan_method_changed
            $table->string('entity_type')->nullable();
            $table->foreignId('entity_id')->nullable();
            $table->json('old_value')->nullable();
            $table->json('new_value')->nullable();
            $table->string('ip_address')->nullable();
            $table->text('user_agent')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('audit_log');
        Schema::dropIfExists('notification_log');
        Schema::dropIfExists('patrol_contacts');
        Schema::dropIfExists('offline_sync_queue');
        Schema::dropIfExists('guard_location_pings');
        Schema::dropIfExists('sos_location_pings');
        Schema::dropIfExists('sos_alerts');
        Schema::dropIfExists('incident_media');
        Schema::dropIfExists('incidents');
        Schema::dropIfExists('checkpoint_media');
        Schema::dropIfExists('patrol_checkpoint_logs');
        Schema::dropIfExists('patrols');
        Schema::dropIfExists('route_assignments');
        Schema::dropIfExists('guard_otp_tokens');
        Schema::dropIfExists('guards');
        Schema::dropIfExists('route_checkpoints');
        Schema::dropIfExists('routes');
        Schema::dropIfExists('checkpoints');
        Schema::dropIfExists('locations');
    }
};
