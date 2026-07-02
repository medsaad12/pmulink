<?php

use App\Http\Controllers\DashboardController;
use App\Http\Controllers\FaitMarquantController;
use App\Http\Controllers\Auth\ZohoAuthController;
use App\Http\Controllers\OrganizationAdminController;
use App\Http\Controllers\OrganizationController;
use App\Http\Controllers\RoleController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\WhiteboardController;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return redirect()->route(Auth::check() ? 'whiteboard' : 'login');
})->name('home');

Route::middleware(['guest'])->group(function () {
    Route::get('auth/zoho/redirect', [ZohoAuthController::class, 'redirect'])->name('auth.zoho.redirect');
    Route::get('auth/zoho/callback', [ZohoAuthController::class, 'callback'])->name('auth.zoho.callback');
});

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('dashboard', [DashboardController::class, 'index'])
        ->middleware('sup')
        ->name('dashboard');
    Route::get('whiteboard', WhiteboardController::class)->name('whiteboard');

    Route::get('organizations/select', [OrganizationController::class, 'select'])->name('organizations.select');
    Route::post('organizations/switch', [OrganizationController::class, 'switch'])->name('organizations.switch');

    Route::middleware('sup')->group(function () {
        Route::get('admin/organizations', [OrganizationAdminController::class, 'index'])->name('admin.organizations.index');
        Route::post('admin/organizations', [OrganizationAdminController::class, 'store'])->name('admin.organizations.store');
        Route::put('admin/organizations/{organization}', [OrganizationAdminController::class, 'update'])->name('admin.organizations.update');
        Route::delete('admin/organizations/{organization}', [OrganizationAdminController::class, 'destroy'])->name('admin.organizations.destroy');
        Route::post('admin/organizations/{organization}/members', [OrganizationAdminController::class, 'attachMember'])->name('admin.organizations.members.attach');
        Route::put('admin/organizations/{organization}/members/{user}', [OrganizationAdminController::class, 'updateMember'])->name('admin.organizations.members.update');
        Route::delete('admin/organizations/{organization}/members/{user}', [OrganizationAdminController::class, 'detachMember'])->name('admin.organizations.members.detach');
        Route::post('admin/organizations/{organization}/departments', [OrganizationAdminController::class, 'storeDepartment'])->name('admin.organizations.departments.store');
        Route::put('admin/organizations/{organization}/departments/{department}', [OrganizationAdminController::class, 'updateDepartment'])->name('admin.organizations.departments.update');
        Route::delete('admin/organizations/{organization}/departments/{department}', [OrganizationAdminController::class, 'destroyDepartment'])->name('admin.organizations.departments.destroy');
    });

    Route::post('faits-marquants', [FaitMarquantController::class, 'store'])->name('faits-marquants.store');
    Route::get('faits-marquants/{faitMarquant}/weekly-timeline', [FaitMarquantController::class, 'weeklyTimeline'])->name('faits-marquants.weekly-timeline');
    Route::put('faits-marquants/{faitMarquant}', [FaitMarquantController::class, 'update'])->name('faits-marquants.update');
    Route::put('faits-marquants/{faitMarquant}/draft', [FaitMarquantController::class, 'saveDraft'])->name('faits-marquants.draft.save');
    Route::post('faits-marquants/drafts/submit-all', [FaitMarquantController::class, 'submitAllDrafts'])->name('faits-marquants.drafts.submit-all');
    Route::put('faits-marquants/{faitMarquant}/sticky-sync', [FaitMarquantController::class, 'syncSticky'])->name('faits-marquants.sticky-sync');
    Route::delete('faits-marquants/{faitMarquant}', [FaitMarquantController::class, 'destroy'])->name('faits-marquants.destroy');

    Route::get('users', [UserController::class, 'index'])
        ->middleware('permission:users.view')
        ->name('users.index');
    Route::post('users', [UserController::class, 'store'])
        ->middleware('permission:users.create')
        ->name('users.store');
    Route::put('users/{user}', [UserController::class, 'update'])
        ->middleware('permission:users.edit')
        ->name('users.update');
    Route::delete('users/{user}', [UserController::class, 'destroy'])
        ->middleware('permission:users.delete')
        ->name('users.destroy');

    Route::get('roles', [RoleController::class, 'index'])
        ->middleware('permission:roles.view')
        ->name('roles.index');
    Route::post('roles', [RoleController::class, 'store'])
        ->middleware('permission:roles.create')
        ->name('roles.store');
    Route::put('roles/{role}', [RoleController::class, 'update'])
        ->middleware('permission:roles.edit')
        ->name('roles.update');
    Route::put('roles/{role}/permissions', [RoleController::class, 'syncPermissions'])
        ->middleware('permission:roles.assign-permissions')
        ->name('roles.permissions.sync');
    Route::delete('roles/{role}', [RoleController::class, 'destroy'])
        ->middleware('permission:roles.delete')
        ->name('roles.destroy');

});

require __DIR__.'/settings.php';
