<?php

declare(strict_types=1);

namespace App\Http\Requests\WorkspaceInvitations;

use App\Models\User;
use App\Models\WorkspaceInvitation;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Password;

final class AcceptInvitationRequest extends FormRequest
{
    /**
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        $invitation = WorkspaceInvitation::findByRawToken((string) $this->route('token'));

        $requiresCredentials = $invitation instanceof WorkspaceInvitation
            && ! $invitation->isExpired()
            && ! $invitation->isAccepted()
            && User::query()->whereEmail($invitation->email)->doesntExist();

        $mode = $requiresCredentials ? 'required' : 'nullable';

        return [
            'name' => [$mode, 'string', 'max:255'],
            'password' => [$mode, Password::defaults()],
            'password_confirmation' => [$mode, 'same:password'],
        ];
    }
}
