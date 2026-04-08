<?php

declare(strict_types=1);

namespace App\Http\Controllers\WorkspaceInvitations;

use App\Actions\WorkspaceInvitations\AcceptInvitationAction;
use App\Http\Requests\WorkspaceInvitations\AcceptInvitationRequest;
use App\Traits\APIResponder;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpKernel\Exception\HttpException;

final readonly class AcceptInvitationController
{
    use APIResponder;

    public function __invoke(AcceptInvitationRequest $request, AcceptInvitationAction $action, string $token): JsonResponse
    {
        $name = $request->input('name') ?? '';
        $password = $request->input('password') ?? '';

        try {
            $user = $action->handle($name, $password, $token);
        } catch (HttpException $httpException) {
            return $this->fail($httpException->getMessage(), $httpException->getStatusCode());
        }

        Auth::login($user);

        return $this->success([
            'user' => $user,
            'workspace' => $user->workspaces->first(),
        ], 'Invitation accepted');
    }
}
