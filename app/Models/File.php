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
        'tags',
        'accessed_at',
        'share_token',
        'share_expires_at',
        'share_password',
        'share_allow_download',
        'share_allow_import',
        'share_allow_direct_access',
    ];

    protected $casts = [
        'is_folder' => 'boolean',
        'is_shared' => 'boolean',
        'is_starred' => 'boolean',
        'tags' => 'array',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime',
        'accessed_at' => 'datetime',
        'share_expires_at' => 'datetime',
        'share_allow_download' => 'boolean',
        'share_allow_import' => 'boolean',
        'share_allow_direct_access' => 'boolean',
    ];

    protected static function booted()
    {
        static::creating(function ($file) {
            if (empty($file->accessed_at)) {
                $file->accessed_at = now();
            }
        });
    }

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

    /**
     * Check if file is publicly shared and not expired
     */
    public function isPublic()
    {
        if (empty($this->share_token)) {
            return false;
        }
        if ($this->share_expires_at && now()->greaterThan($this->share_expires_at)) {
            return false;
        }
        return true;
    }
}
