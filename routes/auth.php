<?php

declare(strict_types=1);

use App\Http\Controllers\Auth\SessionController;
use App\Http\Controllers\Auth\UserController;
use App\Http\Controllers\Auth\UserEmailVerificationController;
use App\Http\Controllers\Auth\UserEmailVerificationNotificationController;
use App\Http\Controllers\Auth\UserPasswordController;
use App\Http\Controllers\Auth\UserProfileController;
use App\Http\Controllers\Auth\UserTwoFactorAuthenticationController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\Repositories\GetConnectedRepositoriesController;
use App\Http\Controllers\Repositories\RepositoryController;
use App\Http\Controllers\Reviews\ReviewController;
use App\Http\Controllers\WorkspaceInvitations\WorkspaceInvitationController;
use App\Http\Controllers\Workspaces\GetWorkspaceMembersController;
use App\Http\Controllers\Workspaces\WorkspaceController;
use App\Http\Controllers\Workspaces\WorkspacePageController;
use App\Http\Controllers\Workspaces\WorkspaceSwitchController;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::middleware(['auth', 'verified'])->group(function (): void {
    Route::get('dashboard', fn () => Inertia::render('dashboard'))->name('dashboard');

    // Notifications...
    Route::controller(NotificationController::class)->group(function (): void {
        Route::get('notifications', 'index')->name('notifications.index');
        Route::post('notifications/{id}/read', 'markAsRead')->name('notifications.mark-read');
        Route::post('notifications/read-all', 'markAllAsRead')->name('notifications.mark-all-read');
    });

    // Workspaces...
    Route::controller(WorkspaceController::class)->group(function (): void {
        Route::get('workspaces', 'index')->name('workspaces.index');
        Route::get('workspaces/create', 'create')->name('workspaces.create');
        Route::post('workspaces', 'store')->name('workspaces.store');
        Route::get('workspaces/{workspace}', 'show')->name('workspaces.show');
    });

    Route::controller(WorkspacePageController::class)->group(function (): void {
        Route::get('workspaces/{workspace}/members', 'members')->name('workspaces.members.page');
        Route::get('workspaces/{workspace}/invitations', 'invitations')->name('workspaces.invitations.page');
        Route::get('workspaces/{workspace}/repos', 'repos')->name('workspaces.repos.page');
    });

    // Workspace Invitations API...
    Route::controller(WorkspaceInvitationController::class)->group(function (): void {
        Route::get('workspaces/{workspace}/invitations/data', 'index')->name('workspaces.invitations');
        Route::post('workspaces/{workspace}/invitations', 'store')->name('workspaces.invitations.store');
        Route::delete('workspaces/{workspace}/invitations/{invitation}', 'destroy')->name('workspaces.invitations.destroy');
    });

    // Workspace Switch ...
    Route::post('workspaces/{workspace}/select', WorkspaceSwitchController::class)->name('workspaces.select');

    // Workspace Members API...
    Route::get('workspaces/{workspace}/members/data', GetWorkspaceMembersController::class)->name('workspaces.members');

    // Workspace Repos API...
    Route::get('workspaces/{workspace}/repos/data', GetConnectedRepositoriesController::class)->name('workspaces.repos');

    // Repositories...
    Route::get('repos', fn () => Inertia::render('repos/index'))->name('repos.index');

    Route::controller(RepositoryController::class)->group(function (): void {
        Route::get('repos/data', 'index')->name('repos.data');
        Route::post('workspaces/{workspace}/repos/{fullName}', 'store')->name('repos.store')->where('fullName', '.+');
        Route::delete('workspaces/{workspace}/repos/{fullName}', 'destroy')->name('repos.destroy')->where('fullName', '.+');
    });

    // Reviews...
    Route::get('workspaces/{workspace}/reviews/{pullRequest}/data', [ReviewController::class, 'show'])->name('reviews.show.data');
    Route::get('workspaces/{workspace}/reviews/data', [ReviewController::class, 'index'])->name('reviews.index');
    Route::get('workspaces/{workspace}/reviews', [WorkspacePageController::class, 'reviews'])->name('reviews.page');
    Route::get('workspaces/{workspace}/reviews/{pullRequest}', [WorkspacePageController::class, 'review'])->name('reviews.show');

    // Invitations...

    // User Settings...
    Route::delete('user', [UserController::class, 'destroy'])->name('user.destroy');

    Route::controller(UserProfileController::class)->group(function (): void {
        Route::redirect('settings', '/settings/profile');
        Route::get('settings/profile', 'edit')->name('user-profile.edit');
        Route::patch('settings/profile', 'update')->name('user-profile.update');
    });

    Route::controller(UserPasswordController::class)->group(function (): void {
        Route::get('settings/password', 'edit')->name('password.edit');
        Route::put('settings/password', 'update')
            ->middleware('throttle:6,1')
            ->name('password.update');
    });

    // Appearance...
    Route::get('settings/appearance', fn () => Inertia::render('appearance/update'))->name('appearance.edit');

    // User Two-Factor Authentication...
    Route::get('settings/two-factor', [UserTwoFactorAuthenticationController::class, 'show'])
        ->name('two-factor.show');

    // Session...
    Route::post('logout', [SessionController::class, 'destroy'])->name('logout');
});

// User Email Verification (requires auth but NOT verified - for unverified users)
Route::middleware('auth')->group(function (): void {
    Route::controller(UserEmailVerificationNotificationController::class)->group(function (): void {
        Route::get('verify-email', 'create')->name('verification.notice');
        Route::post('email/verification-notification', 'store')
            ->middleware('throttle:6,1')
            ->name('verification.send');
    });

    Route::get('verify-email/{id}/{hash}', [UserEmailVerificationController::class, 'update'])
        ->middleware(['signed', 'throttle:6,1'])
        ->name('verification.verify');
});
