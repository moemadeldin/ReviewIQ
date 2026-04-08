<?php

declare(strict_types=1);

namespace App\Http\Requests\Repositories;

use App\Models\User;
use App\Models\Workspace;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Container\Attributes\RouteParameter;
use Illuminate\Foundation\Http\FormRequest;

final class ManageRepositoryRequest extends FormRequest
{
    public function authorize(#[CurrentUser()] User $user, #[RouteParameter('workspace')] Workspace $workspace): bool
    {
        return $workspace->owner->is($user);
    }
}
