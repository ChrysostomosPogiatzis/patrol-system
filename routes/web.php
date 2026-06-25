<?php

use App\Http\Controllers\ProfileController;
use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::get('/', function () {
    return Inertia::render('Welcome', [
        'canLogin' => Route::has('login'),
        'canRegister' => Route::has('register'),
        'laravelVersion' => Application::VERSION,
        'phpVersion' => PHP_VERSION,
    ]);
});

Route::get('/dashboard', function () {
    return Inertia::render('Admin/Dashboard');
})->middleware(['auth', 'verified', 'subscription.active'])->name('dashboard');

Route::middleware(['auth', 'verified', 'subscription.active'])->group(function () {
    Route::get('/admin/guards', function () {
        return Inertia::render('Admin/Guards');
    })->name('admin.guards');

    Route::get('/admin/checkpoints', function () {
        return Inertia::render('Admin/Checkpoints');
    })->name('admin.checkpoints');

    Route::get('/admin/routes', function () {
        return Inertia::render('Admin/Routes');
    })->name('admin.routes');

    Route::get('/admin/contacts', function () {
        return Inertia::render('Admin/Contacts');
    })->name('admin.contacts');

    Route::get('/admin/history', function () {
        return Inertia::render('Admin/History');
    })->name('admin.history');

    Route::get('/admin/subscription', function () {
        return Inertia::render('Admin/Subscription');
    })->name('admin.subscription');

    Route::get('/admin/superadmin', function () {
        return Inertia::render('Admin/SuperadminConsole');
    })->name('admin.superadmin');
});

Route::middleware(['auth', 'subscription.active'])->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');

    // Admin Operations Center API Endpoints
    Route::prefix('admin/api')->group(function () {
        Route::get('/overview', [\App\Http\Controllers\Api\AdminDashboardController::class, 'overview']);
        Route::post('/superadmin/switch-tenant', [\App\Http\Controllers\Api\AdminDashboardController::class, 'switchTenant']);
        
        // Guards
        Route::get('/guards', [\App\Http\Controllers\Api\AdminDashboardController::class, 'listGuards']);
        Route::post('/guards', [\App\Http\Controllers\Api\AdminDashboardController::class, 'createGuard']);
        Route::put('/guards/{id}', [\App\Http\Controllers\Api\AdminDashboardController::class, 'updateGuard']);
        Route::delete('/guards/{id}', [\App\Http\Controllers\Api\AdminDashboardController::class, 'deleteGuard']);
        
        // Locations & Checkpoints
        Route::get('/locations-data', [\App\Http\Controllers\Api\AdminDashboardController::class, 'locationsData']);
        Route::post('/locations', [\App\Http\Controllers\Api\AdminDashboardController::class, 'createLocation']);
        Route::put('/locations/{id}', [\App\Http\Controllers\Api\AdminDashboardController::class, 'updateLocation']);
        Route::delete('/locations/{id}', [\App\Http\Controllers\Api\AdminDashboardController::class, 'deleteLocation']);
        
        Route::post('/checkpoints', [\App\Http\Controllers\Api\AdminDashboardController::class, 'createCheckpoint']);
        Route::put('/checkpoints/{id}', [\App\Http\Controllers\Api\AdminDashboardController::class, 'updateCheckpoint']);
        Route::delete('/checkpoints/{id}', [\App\Http\Controllers\Api\AdminDashboardController::class, 'deleteCheckpoint']);
        
        // Routes
        Route::post('/routes', [\App\Http\Controllers\Api\AdminDashboardController::class, 'createRoute']);
        Route::put('/routes/{id}', [\App\Http\Controllers\Api\AdminDashboardController::class, 'updateRoute']);
        Route::delete('/routes/{id}', [\App\Http\Controllers\Api\AdminDashboardController::class, 'deleteRoute']);
        
        // Route Assignments
        Route::get('/guards/{guardId}/assignments', [\App\Http\Controllers\Api\AdminDashboardController::class, 'listGuardAssignments']);
        Route::post('/assignments', [\App\Http\Controllers\Api\AdminDashboardController::class, 'createAssignment']);
        Route::delete('/assignments/{id}', [\App\Http\Controllers\Api\AdminDashboardController::class, 'deleteAssignment']);
        
        // Resolves
        Route::post('/incidents/{incident}/resolve', [\App\Http\Controllers\Api\AdminDashboardController::class, 'resolveIncident']);
        Route::post('/sos/{sos_alert}/resolve', [\App\Http\Controllers\Api\AdminDashboardController::class, 'resolveSos']);
        
        // Contacts
        Route::get('/contacts', [\App\Http\Controllers\Api\AdminDashboardController::class, 'listContacts']);
        Route::post('/contacts', [\App\Http\Controllers\Api\AdminDashboardController::class, 'createContact']);
        Route::put('/contacts/{id}', [\App\Http\Controllers\Api\AdminDashboardController::class, 'updateContact']);
        Route::delete('/contacts/{id}', [\App\Http\Controllers\Api\AdminDashboardController::class, 'deleteContact']);

        // History
        Route::get('/history', [\App\Http\Controllers\Api\AdminDashboardController::class, 'historyData']);

        // Subscription & Superadmin Management
        Route::get('/subscription', [\App\Http\Controllers\Api\AdminDashboardController::class, 'subscriptionData']);
        Route::put('/company', [\App\Http\Controllers\Api\AdminDashboardController::class, 'updateCompany']);
        Route::get('/superadmin/tenants', [\App\Http\Controllers\Api\AdminDashboardController::class, 'listTenants']);
        Route::put('/superadmin/tenants/{id}/plan', [\App\Http\Controllers\Api\AdminDashboardController::class, 'updateTenantPlan']);
        Route::get('/superadmin/plans', [\App\Http\Controllers\Api\AdminDashboardController::class, 'listPlans']);
        Route::put('/superadmin/plans/{id}', [\App\Http\Controllers\Api\AdminDashboardController::class, 'updatePlan']);
    });
});

Route::get('/guard', function () {
    return Inertia::render('Guard/App');
})->name('guard.app');

require __DIR__.'/auth.php';

