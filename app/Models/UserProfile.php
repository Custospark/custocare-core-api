<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class UserProfile extends Model
{
use HasFactory, SoftDeletes;

    protected $fillable = [
        'user_id',
        'first_name',
        'last_name',
        'display_name',
        'dob',
        'gender',
        'address_line1',
        'address_line2',
        'city',
        'state',
        'country',
        'postal_code',
        'metadata'
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
