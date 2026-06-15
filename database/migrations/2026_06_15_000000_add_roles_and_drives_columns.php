<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // 1. Create projects table
        Schema::create('projects', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->text('description')->nullable();
            $table->foreignId('created_by')->constrained('users')->onDelete('cascade');
            $table->timestamps();
        });

        // 2. Create project_users table
        Schema::create('project_users', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained('projects')->onDelete('cascade');
            $table->foreignId('user_id')->constrained('users')->onDelete('cascade');
            $table->string('role')->default('member'); // member, manager
            $table->timestamps();
            
            $table->unique(['project_id', 'user_id']);
        });

        // 3. Add role column to users table
        Schema::table('users', function (Blueprint $table) {
            if (!Schema::hasColumn('users', 'role')) {
                $table->string('role')->default('user')->after('company'); // superadmin, admin, user
            }
        });

        // 4. Add drive_type and project_id to files table
        Schema::table('files', function (Blueprint $table) {
            if (!Schema::hasColumn('files', 'drive_type')) {
                $table->string('drive_type')->default('personal')->after('is_shared');
            }
            if (!Schema::hasColumn('files', 'project_id')) {
                $table->foreignId('project_id')->nullable()->after('drive_type')->constrained('projects')->onDelete('cascade');
            }
        });

        // 5. Default existing files to 'personal' drive_type
        DB::table('files')->update(['drive_type' => 'personal']);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('files', function (Blueprint $table) {
            if (Schema::hasColumn('files', 'project_id')) {
                $table->dropForeign(['project_id']);
                $table->dropColumn('project_id');
            }
            if (Schema::hasColumn('files', 'drive_type')) {
                $table->dropColumn('drive_type');
            }
        });

        Schema::table('users', function (Blueprint $table) {
            if (Schema::hasColumn('users', 'role')) {
                $table->dropColumn('role');
            }
        });

        Schema::dropIfExists('project_users');
        Schema::dropIfExists('projects');
    }
};
