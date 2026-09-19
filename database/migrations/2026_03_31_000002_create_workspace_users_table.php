<?php

declare(strict_types=1);

use App\Enums\Roles;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('workspace_users', function (Blueprint $table): void {
            $table->foreignUuid('workspace_id')
            ->nullable()
            ->constrained('workspaces')
            ->cascadeOnDelete();
            $table->foreignUuid('user_id')
            ->nullable()
            ->constrained('users')
            ->cascadeOnDelete();
            $table->string('role')
            ->nullable()
            ->default(Roles::Member->value)
            ->index();
            $table->timestamps();

            $table->primary(['workspace_id', 'user_id']);
            $table->index('user_id');
        });
    }
};
