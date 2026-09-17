<?php

declare(strict_types=1);

namespace App\Http\Controllers\WorkspaceInvitations;

use App\Actions\WorkspaceInvitations\AcceptInvitationForUser;
use App\Models\User;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

final readonly class AcceptInvitationAsMemberController
{
    public function __invoke(
        Request $request,
        #[CurrentUser()] User $user,
        AcceptInvitationForUser $action,
        string $token,
    ): RedirectResponse {
        $workspace = $action->handle($user, $token);

        $request->session()->put('current_workspace_id', $workspace->id);

        return to_route('dashboard');
    }
}
