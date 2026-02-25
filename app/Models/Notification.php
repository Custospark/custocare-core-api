<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Notification extends Model
{
    use HasFactory;
    protected $table = 'notifications'; // explicitly define if necessary
    protected $primaryKey = 'id'; //

    protected $fillable = [
        'user_id',
        'title',
        'message',
        'channel',
        'target_type',
        'is_read',
        'read_at',
        'sent_at',
    ];

    protected $dates = [
        'read_at',
        'sent_at',
    ];

    /**
     * The user this notification belongs to (if user-specific).
     */

            public function readers()
        {
            return $this->belongsToMany(User::class, 'notification_user')
                        ->withPivot('read_at')
                        ->withTimestamps();
        }
        

        public function isReadBy(User $user)
        {
            return $this->readers()->where('user_id', $user->id)->whereNotNull('read_at')->exists();
        }
        public function scopeUnreadBy($query, $userId)
        {
            return $query->whereDoesntHave('readers', function ($q) use ($userId) {
                $q->where('user_id', $userId)->whereNotNull('read_at');
            });
        }


        public function user()
        {
            return $this->belongsTo(User::class);
        }
}

