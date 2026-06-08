<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

use App\Models\File;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Schedule;

Schedule::call(function () {
    $expiredItems = File::onlyTrashed()->where('deleted_at', '<=', now()->subDays(40))->get();
    
    $forceDeleteRecursive = function ($file) use (&$forceDeleteRecursive) {
        if ($file->is_folder) {
            $children = File::withTrashed()->where('parent_id', $file->id)->get();
            foreach ($children as $child) {
                $forceDeleteRecursive($child);
            }
        } else {
            if ($file->storage_path) {
                Storage::disk('public')->delete($file->storage_path);
            }
        }
        $file->forceDelete();
    };

    foreach ($expiredItems as $item) {
        $forceDeleteRecursive($item);
    }
})->daily();
