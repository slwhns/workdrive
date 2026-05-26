<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('shares', function (Blueprint $table) {
            $table->id();
            $table->foreignId('file_id')->constrained('files')->onDelete('cascade');
            $table->foreignId('shared_by')->constrained('users')->onDelete('cascade');
            $table->foreignId('shared_with')->constrained('users')->onDelete('cascade');
            $table->enum('permission', ['view', 'edit'])->default('view');
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();
            
            $table->unique(['file_id', 'shared_with']);
            $table->index('file_id');
            $table->index('shared_by');
            $table->index('shared_with');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('shares');
    }
};
