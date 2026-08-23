<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('profiles', function (Blueprint $table) {
            $table->id();
            $table->string('username')->unique();
            $table->string('ig_user_id')->nullable()->unique();
            $table->string('full_name')->nullable();
            $table->string('profile_pic_url')->nullable();
            $table->text('biography')->nullable();
            $table->boolean('is_verified')->default(false);
            $table->boolean('is_private')->default(false);
            $table->unsignedBigInteger('followers_count')->nullable();
            $table->unsignedBigInteger('following_count')->nullable();
            $table->unsignedBigInteger('media_count')->nullable();
            $table->timestamp('last_synced_at')->nullable();
            $table->text('last_error')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('profiles');
    }
};
