<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('follow_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('profile_id')->constrained()->cascadeOnDelete();
            $table->foreignId('sync_run_id')->nullable()->constrained('sync_runs')->nullOnDelete();
            $table->string('ig_user_id');
            $table->string('username');
            $table->string('full_name')->nullable();
            $table->string('profile_pic_url')->nullable();
            $table->string('event_type');
            $table->timestamp('occurred_at');
            $table->timestamps();

            $table->index(['profile_id', 'occurred_at']);
            $table->index(['profile_id', 'event_type', 'occurred_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('follow_events');
    }
};
