<?php
// app/Models/AccountRecoveryToken.php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AccountRecoveryToken extends Model
{
    public $timestamps = false;
    
    protected $table = 'account_recovery_tokens';
    
    protected $fillable = [
        'user_id',
        'token_hash',
        'otp_code',
        'type',
        'channel',
        'expires_at',
        'used_at',
    ];
    
    protected $casts = [
        'expires_at' => 'datetime',
        'used_at' => 'datetime',
        'created_at' => 'datetime',
    ];
    
    /**
     * Get the user that owns the token.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
    
    /**
     * Check if token is expired.
     */
    public function isExpired(): bool
    {
        return $this->expires_at->isPast();
    }
    
    /**
     * Check if token is used.
     */
    public function isUsed(): bool
    {
        return $this->used_at !== null;
    }
    
    /**
     * Mark token as used.
     */
    public function markAsUsed(): void
    {
        $this->used_at = now();
        $this->save();
    }
}