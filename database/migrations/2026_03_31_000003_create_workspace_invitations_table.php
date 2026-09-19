<?php

declare(strict_types=1);

use App\Enums\Roles;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('workspace_invitations', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('workspace_id')
            ->nullable()
            ->constrained('workspaces')
            ->cascadeOnDelete();
            $table->string('email')->nullable();
            $table->string('token')->nullable()->unique();
            $table->string('role')->nullable()->default(Roles::Member->value);
            $table->timestamp('expires_at')->nullable();
            $table->timestamp('accepted_at')->nullable();
            $table->timestamps();
        });
    }
};
