<?php

use App\Http\Controllers\Api\EmergencyController;
use App\Http\Controllers\Api\GuardAuthController;
use App\Http\Controllers\Api\IncidentController;
use App\Http\Controllers\Api\PatrolController;
use App\Http\Controllers\Api\SyncController;
use Illuminate\Support\Facades\Route;

// Public routes for Guards
Route::prefix('guard')->group(function () {
    Route::post('/otp/send', [GuardAuthController::class, 'sendOtp']);
    Route::post('/login', [GuardAuthController::class, 'login']);

    // Protected Guard routes
    Route::middleware(['auth:sanctum', 'subscription.active'])->group(function () {
        Route::get('/me', [GuardAuthController::class, 'me']);
        Route::get('/routes', [PatrolController::class, 'routes']);
        
        // Active Patrol Session management
        Route::get('/patrols/active', [PatrolController::class, 'activePatrol']);
        Route::get('/patrols/history', [PatrolController::class, 'patrolHistory']);
        Route::get('/patrols/{patrol}', [PatrolController::class, 'patrolDetail']);
        Route::post('/patrols/start', [PatrolController::class, 'startPatrol']);
        Route::post('/patrols/{patrol}/checkpoints/{route_checkpoint}/scan', [PatrolController::class, 'scanCheckpoint']);
        Route::post('/patrols/{patrol}/checkpoints/{route_checkpoint}/skip', [PatrolController::class, 'skipCheckpoint']);
        Route::post('/patrols/{patrol}/note', [PatrolController::class, 'addGeneralNote']);
        Route::post('/patrols/{patrol}/complete', [PatrolController::class, 'completePatrol']);
        
        // Incidents
        Route::post('/patrols/{patrol}/incidents', [IncidentController::class, 'reportIncident']);
        Route::post('/incidents/standalone', [IncidentController::class, 'reportStandaloneIncident']);
        
        // Emergencies and Location Pings
        Route::post('/location/ping', [EmergencyController::class, 'pingLocation']);
        Route::post('/sos/trigger', [EmergencyController::class, 'triggerSos']);
        Route::post('/sos/{sos_alert}/ping', [EmergencyController::class, 'pingSosLocation']);
        Route::post('/sos/{sos_alert}/resolve', [EmergencyController::class, 'resolveSos']);
        
        // Offline Synchronization Queue
        Route::post('/sync', [SyncController::class, 'syncQueue']);
    });
});
