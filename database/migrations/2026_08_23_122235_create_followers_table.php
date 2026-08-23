<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('followers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('profile_id')->constrained()->cascadeOnDelete();
            $table->string('ig_user_id');
            $table->string('username');
            $table->string('full_name')->nullable();
            $table->string('profile_pic_url')->nullable();
            $table->boolean('is_verified')->default(false);
            $table->boolean('is_private')->default(false);
            $table->timestamp('first_seen_at');
            $table->timestamp('last_seen_at');
            $table->boolean('is_active')->default(true);
            $table->timestamp('unfollowed_at')->nullable();
            $table->timestamps();

            $table->unique(['profile_id', 'ig_user_id']);
            $table->index(['profile_id', 'is_active']);
            $table->index(['profile_id', 'unfollowed_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('followers');
    }
};
