<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('feed_reports', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->string('feed_url');
            $table->string('feed_title')->nullable();
            $table->unsignedTinyInteger('overall_score')->default(0);
            $table->json('results_json')->nullable();
            $table->string('slug')->unique();
            $table->timestamps();

            $table->index('feed_url');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('feed_reports');
    }
};
