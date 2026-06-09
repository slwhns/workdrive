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
        Schema::table('files', function (Blueprint $table) {
            $table->string('share_token')->nullable()->unique();
            $table->timestamp('share_expires_at')->nullable();
            $table->string('share_password')->nullable();
            $table->boolean('share_allow_download')->default(true);
            $table->boolean('share_allow_import')->default(true);
            $table->boolean('share_allow_direct_access')->default(true);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('files', function (Blueprint $table) {
            $table->dropColumn([
                'share_token',
                'share_expires_at',
                'share_password',
                'share_allow_download',
                'share_allow_import',
                'share_allow_direct_access',
            ]);
        });
    }
};
