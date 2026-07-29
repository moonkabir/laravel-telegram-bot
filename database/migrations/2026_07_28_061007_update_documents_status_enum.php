<?php

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
        Schema::table('documents', function (Blueprint $table) {
            $table->enum('status', ['pending', 'processing', 'completed', 'failed', 'ready'])->default('pending')->change();
        });
        DB::table('documents')->where('status', 'completed')->update(['status' => 'ready']);
        Schema::table('documents', function (Blueprint $table) {
            $table->enum('status', ['pending', 'processing', 'ready', 'failed'])->default('pending')->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('documents', function (Blueprint $table) {
            $table->enum('status', ['pending', 'processing', 'ready', 'failed', 'completed'])->default('pending')->change();
        });
        DB::table('documents')->where('status', 'ready')->update(['status' => 'completed']);
        Schema::table('documents', function (Blueprint $table) {
            $table->enum('status', ['pending', 'processing', 'completed', 'failed'])->default('pending')->change();
        });
    }
};
