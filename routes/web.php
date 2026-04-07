<?php

declare(strict_types=1);

use App\Http\Controllers\Auth\SessionController;
use App\Http\Controllers\Auth\UserController;
use App\Http\Controllers\Auth\UserEmailResetNotificationController;
use App\Http\Controllers\Auth\UserEmailVerificationController;
use App\Http\Controllers\Auth\UserEmailVerificationNotificationController;
use App\Http\Controllers\Auth\UserPasswordController;
use App\Http\Controllers\Auth\UserProfileController;
use App\Http\Controllers\Auth\UserTwoFactorAuthenticationController;
use App\Http\Controllers\GitHubController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\Repositories\GetConnectedRepositoriesController;
use App\Http\Controllers\Repositories\RepositoryController;
use App\Http\Controllers\WorkspaceInvitations\AcceptInvitationController;
use App\Http\Controllers\WorkspaceInvitations\GenerateInvitationController;
use App\Http\Controllers\WorkspaceInvitations\WorkspaceInvitationController;
use App\Http\Controllers\Workspaces\GetWorkspaceMembersController;
use App\Http\Controllers\Workspaces\WorkspaceController;
use App\Http\Controllers\Workspaces\WorkspacePageController;
use App\Http\Controllers\Workspaces\WorkspaceSwitchController;
use App\Models\User;
use App\Models\WorkspaceInvitation;
use Illuminate\Contracts\Routing\ResponseFactory;
use Illuminate\Contracts\View\Factory;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::get('/', fn () => Inertia::render('welcome'))->name('home');

Route::middleware(['auth', 'verified'])->group(function (): void {
    Route::get('dashboard', fn () => Inertia::render('dashboard'))->name('dashboard');

    // Notifications...
    Route::get('notifications', [NotificationController::class, 'index'])->name('notifications.index');
    Route::patch('notifications/{id}/read', [NotificationController::class, 'markAsRead'])->name('notifications.mark-read');
    Route::patch('notifications/read-all', [NotificationController::class, 'markAllAsRead'])->name('notifications.mark-all-read');

    // Workspaces...
    Route::get('workspaces', [WorkspaceController::class, 'index'])->name('workspaces.index');
    Route::get('workspaces/create', [WorkspaceController::class, 'create'])->name('workspaces.create');
    Route::post('workspaces', [WorkspaceController::class, 'store'])->name('workspaces.store');
    Route::post('workspaces/{workspace}/select', WorkspaceSwitchController::class)->name('workspaces.select');
    Route::get('workspaces/{workspace}', [WorkspaceController::class, 'show'])->name('workspaces.show');
    Route::get('workspaces/{workspace}/members', [WorkspacePageController::class, 'members'])->name('workspaces.members.page');
    Route::get('workspaces/{workspace}/repos', [WorkspacePageController::class, 'repos'])->name('workspaces.repos.page');
    Route::get('workspaces/{workspace}/invitations', [WorkspacePageController::class, 'invitations'])->name('workspaces.invitations.page');

    // Workspace Invitations API...
    Route::get('workspaces/{workspace}/invitations/data', [WorkspaceInvitationController::class, 'index'])->name('workspaces.invitations');
    Route::post('workspaces/{workspace}/invitations', [WorkspaceInvitationController::class, 'store'])->name('workspaces.invitations.store');
    Route::delete('workspaces/{workspace}/invitations/{invitation}', [WorkspaceInvitationController::class, 'destroy'])->name('workspaces.invitations.destroy');

    // Workspace Members API...
    Route::get('workspaces/{workspace}/members/data', GetWorkspaceMembersController::class)->name('workspaces.members');

    // Workspace Repos API...
    Route::get('workspaces/{workspace}/repos/data', GetConnectedRepositoriesController::class)->name('workspaces.repos');

    // Repositories...
    Route::get('repos', fn () => Inertia::render('repos/index'))->name('repos.index');
    Route::get('repos/data', [RepositoryController::class, 'index'])->name('repos.data');
    Route::post('repos/{fullName}', [RepositoryController::class, 'store'])->name('repos.store')->where('fullName', '.+');
    Route::delete('repos/{fullName}', [RepositoryController::class, 'destroy'])->name('repos.destroy')->where('fullName', '.+');
    Route::post('repos/toggle', [RepositoryController::class, 'toggle'])->name('repos.toggle');

    // Invitations...
    Route::post('invitations', GenerateInvitationController::class)->name('invitations.store');
});

Route::middleware('auth')->group(function (): void {
    // User...
    Route::delete('user', [UserController::class, 'destroy'])->name('user.destroy');

    // User Profile...
    Route::redirect('settings', '/settings/profile');
    Route::get('settings/profile', [UserProfileController::class, 'edit'])->name('user-profile.edit');
    Route::patch('settings/profile', [UserProfileController::class, 'update'])->name('user-profile.update');

    // User Password...
    Route::get('settings/password', [UserPasswordController::class, 'edit'])->name('password.edit');
    Route::put('settings/password', [UserPasswordController::class, 'update'])
        ->middleware('throttle:6,1')
        ->name('password.update');

    // Appearance...
    Route::get('settings/appearance', fn () => Inertia::render('appearance/update'))->name('appearance.edit');

    // User Two-Factor Authentication...
    Route::get('settings/two-factor', [UserTwoFactorAuthenticationController::class, 'show'])
        ->name('two-factor.show');
});

Route::middleware('guest')->group(function (): void {
    // Invitations...
    Route::get('invitations/{token}/accept', function (string $token): ResponseFactory|Response|Factory|View {
        $invitation = WorkspaceInvitation::query()->where('token', $token)->first();

        if (! $invitation) {
            return response('Invalid invitation', 404);
        }

        if ($invitation->isExpired()) {
            return response('Invitation has expired', 410);
        }

        if ($invitation->isAccepted()) {
            return response('Invitation already used', 409);
        }

        $user = User::query()->where('email', $invitation->email)->first();

        return view('invitations.accept', [
            'invitation' => $invitation,
            'isExistingUser' => $user !== null,
        ]);
    })->name('invitations.accept.page');

    Route::post('invitations/{token}/accept', AcceptInvitationController::class)
        ->name('invitations.accept');

    // GitHub OAuth...
    Route::get('auth/github', [GitHubController::class, 'redirect'])->name('auth.github');
    Route::get('auth/github/callback', [GitHubController::class, 'callback'])->name('auth.github.callback');

    // User...
    Route::get('register', [UserController::class, 'create'])
        ->name('register');
    Route::post('register', [UserController::class, 'store'])
        ->name('register.store');

    // User Password...
    Route::get('reset-password/{token}', [UserPasswordController::class, 'create'])
        ->name('password.reset');
    Route::post('reset-password', [UserPasswordController::class, 'store'])
        ->name('password.store');

    // User Email Reset Notification...
    Route::get('forgot-password', [UserEmailResetNotificationController::class, 'create'])
        ->name('password.request');
    Route::post('forgot-password', [UserEmailResetNotificationController::class, 'store'])
        ->name('password.email');

    // Session...
    Route::get('login', [SessionController::class, 'create'])
        ->name('login');
    Route::post('login', [SessionController::class, 'store'])
        ->name('login.store');
});

Route::middleware('auth')->group(function (): void {
    // User Email Verification...
    Route::get('verify-email', [UserEmailVerificationNotificationController::class, 'create'])
        ->name('verification.notice');
    Route::post('email/verification-notification', [UserEmailVerificationNotificationController::class, 'store'])
        ->middleware('throttle:6,1')
        ->name('verification.send');

    // User Email Verification...
    Route::get('verify-email/{id}/{hash}', [UserEmailVerificationController::class, 'update'])
        ->middleware(['signed', 'throttle:6,1'])
        ->name('verification.verify');

    // Session...
    Route::post('logout', [SessionController::class, 'destroy'])
        ->name('logout');
});
