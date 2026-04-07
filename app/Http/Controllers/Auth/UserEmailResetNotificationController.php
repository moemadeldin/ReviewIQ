<?php

declare(strict_types=1);

namespace App\Http\Controllers\Auth;

use App\Actions\Auth\CreateUserEmailResetNotification;
use App\Http\Requests\Auth\CreateUserEmailResetNotificationRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

final readonly class UserEmailResetNotificationController
{
    public function create(Request $request): Response
    {
        return Inertia::render('user-email-reset-notification/create', [
            'status' => $request->session()->get('status'),
        ]);
    }

    public function store(
        CreateUserEmailResetNotificationRequest $request,
        CreateUserEmailResetNotification $action
    ): RedirectResponse {
        $action->handle(['email' => $request->string('email')->value()]);

        return back()->with('status', __('A reset link will be sent if the account exists.'));
    }
}
