<?php

declare(strict_types=1);

namespace App\Http\Requests\WorkspaceInvitations;

use App\Enums\Roles;
use App\Rules\ValidEmail;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Enum;

final class GenerateInvitationRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'email' => [
                'required',
                'string',
                'lowercase',
                'max:255',
                'email',
                new ValidEmail,
            ],
            'role' => ['nullable', new Enum(Roles::class)],
        ];
    }
}
