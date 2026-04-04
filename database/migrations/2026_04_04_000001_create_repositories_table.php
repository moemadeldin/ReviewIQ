<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('repositories', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('workspace_id')->constrained('workspaces')->cascadeOnDelete();
            $table->string('github_repo_id')->nullable();
            $table->string('full_name')->nullable();
            $table->string('language')->nullable();
            $table->boolean('is_active')->index()->default(true);
            $table->text('custom_rules')->nullable();
            $table->string('webhook_id')->nullable();
            $table->timestamps();

            $table->unique(['workspace_id', 'github_repo_id']);
            $table->index(['workspace_id', 'full_name']);
        });
    }
};
