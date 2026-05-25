<?php

declare(strict_types=1);

use App\Http\Controllers\Auth\SessionController;
use App\Http\Controllers\Auth\UserController;
use App\Http\Controllers\Auth\UserEmailResetNotificationController;
use App\Http\Controllers\Auth\UserPasswordController;
use App\Http\Controllers\GitHubController;
use App\Http\Controllers\WorkspaceInvitations\AcceptInvitationController;
use App\Http\Controllers\WorkspaceInvitations\ShowAcceptInvitationController;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

require __DIR__.'/auth.php';

Route::get('/', fn () => Inertia::render('welcome'))->name('home');

Route::middleware('guest')->group(function (): void {
    // Invitations...
    Route::get('invitations/{token}/accept', ShowAcceptInvitationController::class)->name('invitations.accept.page');

    Route::post('invitations/{token}/accept', AcceptInvitationController::class)
        ->name('invitations.accept');

    // Registration...
    Route::controller(UserController::class)->group(function (): void {
        Route::get('register', 'create')->name('register');
        Route::post('register', 'store')->name('register.store');
    });

    // Password Reset...
    Route::controller(UserPasswordController::class)->group(function (): void {
        Route::get('reset-password/{token}', 'create')->name('password.reset');
        Route::post('reset-password', 'store')->name('password.store');
    });

    // Forgot Password...
    Route::controller(UserEmailResetNotificationController::class)->group(function (): void {
        Route::get('forgot-password', 'create')->name('password.request');
        Route::post('forgot-password', 'store')->name('password.email');
    });

    // Session (Login)...
    Route::controller(SessionController::class)->group(function (): void {
        Route::get('login', 'create')->name('login');
        Route::post('login', 'store')->name('login.store');
    });

});
// GitHub OAuth...

Route::controller(GitHubController::class)->group(function (): void {
    Route::get('auth/github', 'redirect')->name('auth.github');
    Route::get('auth/github/callback', 'callback')->name('auth.github.callback');
});
