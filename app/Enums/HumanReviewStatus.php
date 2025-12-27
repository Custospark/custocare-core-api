<?php

namespace App\Enums;

enum HumanReviewStatus: string
{
    case PENDING_REVIEW = 'pending_review';
    case ACCEPTED = 'accepted';
    case MODIFIED = 'modified';
    case REJECTED = 'rejected';
    case OVERRIDDEN = 'overridden';
    case NOT_APPLICABLE = 'not_applicable';

    public function label(): string
    {
        return match($this) {
            self::PENDING_REVIEW => 'Pending Review',
            self::ACCEPTED => 'Accepted',
            self::MODIFIED => 'Modified',
            self::REJECTED => 'Rejected',
            self::OVERRIDDEN => 'Overridden',
            self::NOT_APPLICABLE => 'Not Applicable',
        };
    }

    public function isCompleted(): bool
    {
        return in_array($this, [
            self::ACCEPTED,
            self::MODIFIED,
            self::REJECTED,
            self::OVERRIDDEN,
            self::NOT_APPLICABLE,
        ]);
    }
}