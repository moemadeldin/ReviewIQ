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
        Schema::table('reviews', function (Blueprint $table): void {
            $table->foreignUuid('previous_review_id')
                ->nullable()
                ->after('pull_request_id')
                ->constrained('reviews')
                ->nullOnDelete();
        });
    }
};
