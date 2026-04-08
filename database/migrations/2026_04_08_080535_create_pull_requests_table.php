<?php

declare(strict_types=1);

use App\Enums\PullRequestStatus;
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
        Schema::create('pull_requests', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('repository_id')->constrained('repositories')->cascadeOnDelete();
            $table->unsignedBigInteger('github_pr_id')->unique();
            $table->string('title')->nullable();
            $table->integer('number')->nullable();
            $table->string('author')->nullable();
            $table->string('diff_url')->nullable();
            $table->string('head_sha', 48)->nullable();
            $table->string('status')->index()->default(PullRequestStatus::Pending->value);
            $table->timestamps();
        });
    }
};
