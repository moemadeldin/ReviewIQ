<?php

declare(strict_types=1);

namespace App\Http\Requests\WorkspaceInvitations;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Password;

final class AcceptInvitationRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'name' => ['nullable', 'string', 'max:255'],
            'password' => [
                'nullable',
                'confirmed',
                Password::defaults(),
            ],
        ];
    }
}
