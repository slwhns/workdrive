<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Project extends Model
{
    protected $fillable = [
        'name',
        'description',
        'created_by',
    ];

    /**
     * Get the members of the project.
     */
    public function members()
    {
        return $this->belongsToMany(User::class, 'project_users')
            ->withPivot('role')
            ->withTimestamps();
    }

    /**
     * Get the user who created the project.
     */
    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
