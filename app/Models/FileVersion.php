<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class FileVersion extends Model
{
    protected $fillable = [
        'file_id',
        'version_number',
        'storage_path',
        'size',
        'created_by',
        'changelog',
    ];

    protected $casts = [
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * Get the file
     */
    public function file()
    {
        return $this->belongsTo(File::class);
    }

    /**
     * Get the user who created this version
     */
    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
