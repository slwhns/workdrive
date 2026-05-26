<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('file_versions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('file_id')->constrained('files')->onDelete('cascade');
            $table->integer('version_number')->default(1);
            $table->string('storage_path');
            $table->bigInteger('size')->default(0);
            $table->foreignId('created_by')->constrained('users')->onDelete('cascade');
            $table->text('changelog')->nullable();
            $table->timestamps();
            
            $table->unique(['file_id', 'version_number']);
            $table->index('file_id');
            $table->index('created_by');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('file_versions');
    }
};
