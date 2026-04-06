<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Models\User;
use App\Models\Workspace;
use Illuminate\Foundation\Http\FormRequest;

final class CancelInvitationRequest extends FormRequest
{
    public function authorize(): bool
    {
        /** @var User $user */
        $user = $this->user();

        if (! $user instanceof User) {
            return false;
        }

        $workspace = Workspace::query()->where('slug', $this->route('workspace'))->first();

        if (! $workspace instanceof Workspace) {
            return false;
        }

        return $workspace->isOwner($user);
    }
}
