<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('files', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('path')->nullable();
            $table->enum('type', ['file', 'folder'])->default('file');
            $table->string('mime_type')->nullable();
            $table->bigInteger('size')->default(0);
            $table->string('storage_path')->nullable();
            $table->foreignId('parent_id')->nullable()->constrained('files')->onDelete('cascade');
            $table->foreignId('created_by')->constrained('users')->onDelete('cascade');
            $table->boolean('is_folder')->default(false);
            $table->boolean('is_shared')->default(false);
            $table->timestamps();
            $table->softDeletes();
            
            $table->index('parent_id');
            $table->index('created_by');
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('files');
    }
};
