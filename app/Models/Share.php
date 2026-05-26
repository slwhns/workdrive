<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Share extends Model
{
    protected $fillable = [
        'file_id',
        'shared_by',
        'shared_with',
        'permission',
        'expires_at',
    ];

    protected $casts = [
        'expires_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * Get the file being shared
     */
    public function file()
    {
        return $this->belongsTo(File::class);
    }

    /**
     * Get user who shared the file
     */
    public function sharedBy()
    {
        return $this->belongsTo(User::class, 'shared_by');
    }

    /**
     * Get user the file is shared with
     */
    public function sharedWith()
    {
        return $this->belongsTo(User::class, 'shared_with');
    }
}
