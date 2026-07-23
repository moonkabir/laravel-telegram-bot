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
        // Migration for documents table
        Schema::create('documents', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('file_path');
            $table->string('file_type');
            $table->unsignedBigInteger('file_size');
            $table->text('extracted_text')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();
        });

        // Migration for document_chunks table (for vector storage)
        Schema::create('document_chunks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('document_id')->constrained()->onDelete('cascade');
            $table->text('content');
            $table->string('chunk_index');
            $table->json('embedding')->nullable(); // Store vector embeddings
            $table->timestamps();
            $table->index(['document_id', 'chunk_index']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('document_chunks');
        Schema::dropIfExists('documents');
    }
};
