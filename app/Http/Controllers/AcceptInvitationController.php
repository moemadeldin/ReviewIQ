<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\WorkspaceInvitation;
use App\Traits\APIResponder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Symfony\Component\HttpFoundation\Response;

final readonly class AcceptInvitationController
{
    use APIResponder;

    public function __invoke(Request $request, string $token): JsonResponse
    {
        $invitation = WorkspaceInvitation::where('token', $token)->first();

        if (! $invitation) {
            return $this->fail('Invalid invitation', Response::HTTP_NOT_FOUND);
        }

        if ($invitation->isExpired()) {
            return $this->fail('Invitation has expired', Response::HTTP_GONE);
        }

        if ($invitation->isAccepted()) {
            return $this->fail('Invitation already used', Response::HTTP_CONFLICT);
        }

        $user = User::query()->where('email', $invitation->email)->first();

        if (! $user) {
            $validated = $request->validate([
                'name' => ['required', 'string', 'max:255'],
                'password' => ['required', 'string', 'min:8', 'confirmed'],
            ]);

            $user = User::query()->create([
                'name' => $validated['name'],
                'email' => $invitation->email,
                'password' => Hash::make($validated['password']),
            ]);
        }

        $invitation->workspace->addUser($user, $invitation->role);

        $invitation->update(['accepted_at' => now()]);

        auth()->login($user);

        return $this->success([
            'user' => $user,
            'workspace' => $invitation->workspace,
        ], 'Invitation accepted');
    }
}
