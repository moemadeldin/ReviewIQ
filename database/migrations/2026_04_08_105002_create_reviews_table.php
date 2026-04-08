<?php

declare(strict_types=1);

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
        Schema::create('reviews', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('pull_request_id')->constrained('pull_requests')->cascadeOnDelete();
            $table->text('summary')->nullable();
            $table->string('score_rationale')->nullable();
            $table->jsonb('issues')->nullable();
            $table->jsonb('highlights')->nullable();
            $table->integer('score')->nullable();
            $table->string('recommendation')->nullable();
            $table->text('raw_response')->nullable();
            $table->timestamps();
        });
    }
};
