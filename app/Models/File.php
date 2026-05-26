<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class File extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'name',
        'path',
        'type',
        'mime_type',
        'size',
        'storage_path',
        'parent_id',
        'created_by',
        'is_folder',
        'is_shared',
        'is_starred',
    ];

    protected $casts = [
        'is_folder' => 'boolean',
        'is_shared' => 'boolean',
        'is_starred' => 'boolean',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime',
    ];

    /**
     * Get parent folder
     */
    public function parent()
    {
        return $this->belongsTo(File::class, 'parent_id');
    }

    /**
     * Get child files/folders
     */
    public function children()
    {
        return $this->hasMany(File::class, 'parent_id');
    }

    /**
     * Get file versions
     */
    public function versions()
    {
        return $this->hasMany(FileVersion::class);
    }

    /**
     * Get shares for this file
     */
    public function shares()
    {
        return $this->hasMany(Share::class);
    }

    /**
     * Get creator user
     */
    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
