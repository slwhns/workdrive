<?php

namespace Database\Seeders;

use App\Models\User;
use App\Models\File;
use App\Models\Project;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\DB;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // 1. Create Demo Users
        $superadmin = User::create([
            'name' => 'Super Admin',
            'email' => 'superadmin@example.com',
            'password' => Hash::make('password'),
            'role' => 'superadmin',
            'company' => 'WorkDrive Corp',
        ]);

        $admin = User::create([
            'name' => 'Admin User',
            'email' => 'admin@example.com',
            'password' => Hash::make('password'),
            'role' => 'admin',
            'company' => 'WorkDrive Corp',
        ]);

        $google1 = User::create([
            'name' => 'Google User One',
            'email' => 'user1@example.com',
            'password' => Hash::make('password'),
            'role' => 'user',
            'company' => 'Google',
        ]);

        $google2 = User::create([
            'name' => 'Google User Two',
            'email' => 'user2@example.com',
            'password' => Hash::make('password'),
            'role' => 'user',
            'company' => 'Google',
        ]);

        $apple = User::create([
            'name' => 'Apple User One',
            'email' => 'user3@example.com',
            'password' => Hash::make('password'),
            'role' => 'user',
            'company' => 'Apple',
        ]);

        // 2. Create Projects & Add Members
        // Project Alpha (Google 1 is manager, Google 2 is member)
        $projectAlpha = Project::create([
            'name' => 'Project Alpha',
            'description' => 'Confidential search engine tasks',
            'created_by' => $google1->id,
        ]);

        DB::table('project_users')->insert([
            ['project_id' => $projectAlpha->id, 'user_id' => $google1->id, 'role' => 'manager', 'created_at' => now(), 'updated_at' => now()],
            ['project_id' => $projectAlpha->id, 'user_id' => $google2->id, 'role' => 'member', 'created_at' => now(), 'updated_at' => now()],
        ]);

        // Project Beta (Apple 1 is manager)
        $projectBeta = Project::create([
            'name' => 'Project Beta',
            'description' => 'Next-gen silicon architecture specs',
            'created_by' => $apple->id,
        ]);

        DB::table('project_users')->insert([
            ['project_id' => $projectBeta->id, 'user_id' => $apple->id, 'role' => 'manager', 'created_at' => now(), 'updated_at' => now()],
        ]);

        // 3. Create Root Folders for Projects in Files Table
        $alphaFolder = File::create([
            'name' => 'Project Alpha',
            'type' => 'folder',
            'is_folder' => true,
            'parent_id' => null,
            'created_by' => $google1->id,
            'drive_type' => 'project',
            'project_id' => $projectAlpha->id,
        ]);

        $betaFolder = File::create([
            'name' => 'Project Beta',
            'type' => 'folder',
            'is_folder' => true,
            'parent_id' => null,
            'created_by' => $apple->id,
            'drive_type' => 'project',
            'project_id' => $projectBeta->id,
        ]);

        // 4. Seed Files and Subfolders inside Projects
        File::create([
            'name' => 'Alpha Roadmap.docx',
            'type' => 'file',
            'mime_type' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'size' => 1024,
            'storage_path' => 'drive/uploads/alpha-roadmap.docx',
            'parent_id' => $alphaFolder->id,
            'created_by' => $google1->id,
            'drive_type' => 'project',
            'project_id' => $projectAlpha->id,
        ]);

        File::create([
            'name' => 'Beta Silicon Spec.pdf',
            'type' => 'file',
            'mime_type' => 'application/pdf',
            'size' => 2048,
            'storage_path' => 'drive/uploads/beta-silicon.pdf',
            'parent_id' => $betaFolder->id,
            'created_by' => $apple->id,
            'drive_type' => 'project',
            'project_id' => $projectBeta->id,
        ]);

        // 5. Seed Personal Files/Folders
        File::create([
            'name' => 'Google1 Personal Diary.docx',
            'type' => 'file',
            'mime_type' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'size' => 512,
            'storage_path' => 'drive/uploads/g1-diary.docx',
            'parent_id' => null,
            'created_by' => $google1->id,
            'drive_type' => 'personal',
        ]);

        File::create([
            'name' => 'Apple Personal Finance.xlsx',
            'type' => 'file',
            'mime_type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'size' => 1536,
            'storage_path' => 'drive/uploads/apple-finance.xlsx',
            'parent_id' => null,
            'created_by' => $apple->id,
            'drive_type' => 'personal',
        ]);

        // 6. Seed Organization Files/Folders
        File::create([
            'name' => 'Google Org Policies.pdf',
            'type' => 'file',
            'mime_type' => 'application/pdf',
            'size' => 3072,
            'storage_path' => 'drive/uploads/google-policies.pdf',
            'parent_id' => null,
            'created_by' => $google1->id,
            'drive_type' => 'organization',
        ]);

        File::create([
            'name' => 'Apple Org Handout.docx',
            'type' => 'file',
            'mime_type' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'size' => 256,
            'storage_path' => 'drive/uploads/apple-handout.docx',
            'parent_id' => null,
            'created_by' => $apple->id,
            'drive_type' => 'organization',
        ]);
    }
}
