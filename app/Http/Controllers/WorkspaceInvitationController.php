<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\CreateInvitationAction;
use App\Http\Requests\CancelInvitationRequest;
use App\Http\Requests\GenerateInvitationRequest;
use App\Models\User;
use App\Models\Workspace;
use App\Models\WorkspaceInvitation;
use App\Traits\APIResponder;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\JsonResponse;
use RuntimeException;
use Symfony\Component\HttpFoundation\Response;

final readonly class WorkspaceInvitationController
{
    use APIResponder;

    public function index(
        #[CurrentUser()] User $user,
        string $workspace,
    ): JsonResponse {
        $workspaceModel = Workspace::query()->where('slug', $workspace)->first();

        if (! $workspaceModel) {
            return $this->fail('Workspace not found', Response::HTTP_NOT_FOUND);
        }

        $page = (int) request()->query('page', 1);
        $limit = 10;

        $invitations = WorkspaceInvitation::query()
            ->where('workspace_id', $workspaceModel->id)
            ->whereNull('accepted_at')->latest()
            ->simplePaginate($limit, page: $page);

        $items = $invitations->getCollection()->map(fn ($invitation): array => [
            'id' => $invitation->id,
            'email' => $invitation->email,
            'role' => $invitation->role,
            'expires_at' => $invitation->expires_at,
            'created_at' => $invitation->created_at,
        ]);

        return $this->success([
            'invitations' => $items,
            'current_page' => $invitations->currentPage(),
            'has_more' => $invitations->hasMorePages(),
        ], 'ok');
    }

    public function store(
        GenerateInvitationRequest $request,
        #[CurrentUser()] User $user,
        string $workspace,
        CreateInvitationAction $action,
    ): JsonResponse {
        $workspaceModel = Workspace::query()->where('slug', $workspace)->first();

        if (! $workspaceModel) {
            return $this->fail('Workspace not found', Response::HTTP_NOT_FOUND);
        }

        try {
            $invitation = $action->handle(
                $workspaceModel,
                $user,
                $request->validated('email'),
                $request->validated('role'),
            );
        } catch (RuntimeException $runtimeException) {
            return $this->fail($runtimeException->getMessage(), Response::HTTP_CONFLICT);
        }

        return $this->success([
            'invitation' => $invitation,
        ], 'Invitation sent');
    }

    public function destroy(
        CancelInvitationRequest $request,
        #[CurrentUser()] User $user,
        string $workspace,
        string $invitation,
    ): JsonResponse {
        $workspaceModel = Workspace::query()->where('slug', $workspace)->first();

        if (! $workspaceModel) {
            return $this->fail('Workspace not found', Response::HTTP_NOT_FOUND);
        }

        $invitationModel = WorkspaceInvitation::query()->find($invitation);

        if (! $invitationModel) {
            return $this->fail('Invitation not found', Response::HTTP_NOT_FOUND);
        }

        if ($invitationModel->workspace_id !== $workspaceModel->id) {
            return $this->fail('Invitation does not belong to this workspace', Response::HTTP_FORBIDDEN);
        }

        if ($invitationModel->accepted_at !== null) {
            return $this->fail('Cannot cancel accepted invitation', Response::HTTP_CONFLICT);
        }

        $invitationModel->delete();

        return $this->success(['message' => 'Invitation cancelled'], 'ok');
    }

    public function members(
        #[CurrentUser()] User $user,
        string $workspace,
    ): JsonResponse {
        $workspaceModel = Workspace::query()->where('slug', $workspace)->first();

        if (! $workspaceModel) {
            return $this->fail('Workspace not found', Response::HTTP_NOT_FOUND);
        }

        $page = (int) request()->query('page', 1);
        $limit = 10;

        $members = $workspaceModel->users()
            ->latest('workspace_users.created_at')
            ->simplePaginate($limit, page: $page);

        $items = $members->getCollection()->map(fn ($member): array => [
            'id' => $member->id,
            'name' => $member->name,
            'email' => $member->email,
            'avatar' => $member->github_avatar,
            'role' => $member->pivot->role,
            'joined_at' => $member->pivot->created_at,
        ]);

        return $this->success([
            'members' => $items,
            'current_page' => $members->currentPage(),
            'has_more' => $members->hasMorePages(),
        ], 'ok');
    }
}
