<?php

declare(strict_types=1);

namespace App\Http\Requests\WorkspaceInvitations;

use App\Enums\Roles;
use App\Models\User;
use App\Models\Workspace;
use App\Rules\ValidEmail;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Container\Attributes\RouteParameter;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Enum;

final class GenerateInvitationRequest extends FormRequest
{
    public function authorize(#[CurrentUser()] User $user, #[RouteParameter('workspace')] Workspace $workspace): bool
    {
        return $workspace->isOwner($user);
    }

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
                Rule::unique(User::class),
            ],
            'role' => ['required', new Enum(Roles::class)],
        ];
    }
}
