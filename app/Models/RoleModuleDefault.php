<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class RoleModuleDefault extends Model
{
    use HasFactory;

    protected $fillable = [
        'role_code',
        'module_code',
        'default_access',
    ];

}
