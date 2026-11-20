<?php

declare(strict_types=1);

namespace App\Http\Requests\Workspaces;

use App\Models\User;
use App\Models\Workspace;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Container\Attributes\RouteParameter;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

final class UpdateWorkspaceRequest extends FormRequest
{
    public function authorize(#[CurrentUser()] User $user, #[RouteParameter('workspace')] Workspace $workspace): bool
    {
        return $workspace->isOwner($user) || $workspace->isOwnerOrAdmin($user);
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
        ];
    }
}
