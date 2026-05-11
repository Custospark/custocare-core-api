<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class HubFeedbackVote extends Model
{
    protected $fillable = [
        'hub_feedback_request_id',
        'user_id',
    ];

    public function feedbackRequest(): BelongsTo
    {
        return $this->belongsTo(HubFeedbackRequest::class, 'hub_feedback_request_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
