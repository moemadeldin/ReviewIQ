<?php

declare(strict_types=1);

namespace App\Http\Controllers\WorkspaceInvitations;

use App\Actions\WorkspaceInvitations\AcceptInvitationAction;
use App\Http\Requests\WorkspaceInvitations\AcceptInvitationRequest;
use App\Traits\APIResponder;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;

final readonly class AcceptInvitationController
{
    use APIResponder;

    public function __invoke(AcceptInvitationRequest $request, AcceptInvitationAction $action, string $token): JsonResponse
    {
        $data = $request->validated();

        $user = $action->handle($data['name'], $data['password'], $token);

        Auth::login($user);

        return $this->success([
            'user' => $user,
            'workspace' => $user->workspaces->first(),
        ], 'Invitation accepted');
    }
}
