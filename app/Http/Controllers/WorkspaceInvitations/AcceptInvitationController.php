<?php

declare(strict_types=1);

namespace App\Http\Controllers\WorkspaceInvitations;

use App\Actions\WorkspaceInvitations\AcceptInvitationAction;
use App\Http\Requests\WorkspaceInvitations\AcceptInvitationRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;

final readonly class AcceptInvitationController
{
    public function __invoke(AcceptInvitationRequest $request, AcceptInvitationAction $action, string $token): RedirectResponse
    {
        $user = $action->handle(
            name: $request->string('name')->toString(),
            password: $request->string('password')->toString(),
            token: $token,
        );

        Auth::login($user);

        return redirect()->intended(route('dashboard'));
    }
}
