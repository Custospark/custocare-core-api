<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Personal message address book entry (owner-scoped, encrypted contact channels).
 *
 * @property int         $id
 * @property int         $owner_user_id
 * @property string      $display_name
 * @property int|null    $linked_user_id
 * @property string|null $email_encrypted
 * @property string|null $email_hash
 * @property string|null $phone_encrypted
 * @property string|null $phone_hash
 * @property \Illuminate\Support\Carbon|null $last_used_at
 */
class UserMessageContact extends Model
{
    protected $fillable = [
        'owner_user_id',
        'display_name',
        'linked_user_id',
        'email_encrypted',
        'email_hash',
        'phone_encrypted',
        'phone_hash',
        'last_used_at',
    ];

    protected $casts = [
        'last_used_at' => 'datetime',
    ];

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_user_id');
    }

    public function linkedUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'linked_user_id');
    }
}
