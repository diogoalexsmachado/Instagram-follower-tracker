<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sync_runs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('profile_id')->constrained()->cascadeOnDelete();
            $table->timestamp('started_at');
            $table->timestamp('finished_at')->nullable();
            $table->string('status')->default('running');
            $table->unsignedInteger('followers_fetched')->default(0);
            $table->unsignedInteger('added_count')->default(0);
            $table->unsignedInteger('removed_count')->default(0);
            $table->unsignedBigInteger('followers_count_before')->nullable();
            $table->unsignedBigInteger('followers_count_after')->nullable();
            $table->text('error')->nullable();
            $table->timestamps();

            $table->index(['profile_id', 'started_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sync_runs');
    }
};
