<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Organization extends Model
{
    protected $fillable = [
        'name',
        'slug',
        'email'
    ];

    public function users()
    {
        return $this->belongsToMany(User::class, 'organization_users');
    }
}
