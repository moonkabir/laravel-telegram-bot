<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('telegram_messages', function (Blueprint $table) {
            $table->id();

            // User information
            $table->string('user_id')->index();
            $table->string('username')->nullable();
            $table->string('first_name')->nullable();
            $table->string('last_name')->nullable();

            // Message information
            $table->string('message_id')->unique();
            $table->text('message_text')->nullable();
            $table->string('chat_id')->index();
            $table->string('chat_type')->nullable();

            // Response tracking
            $table->text('bot_response')->nullable();
            $table->boolean('is_processed')->default(false);

            // Additional metadata
            $table->json('raw_data')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('telegram_messages');
    }
};
