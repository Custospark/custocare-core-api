<?php

declare(strict_types=1);

namespace App\Http\Resources\Billing;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** Summary row for facility receipt list (approved payments). */
class SubscriptionReceiptResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'                    => $this->id,
            'receipt_number'        => $this->receipt_number,
            'facility_id'           => $this->facility_id,
            'subscription_id'       => $this->subscription_id,
            'invoice_id'            => $this->invoice_id,
            'amount'                => (float) $this->amount,
            'currency'              => $this->currency,
            'payment_type'          => $this->payment_type->value,
            'payment_type_label'    => $this->payment_type->label(),
            'method_label'          => $this->method->label(),
            'transaction_reference' => $this->transaction_reference,
            'approved_at'           => $this->approved_at?->toISOString(),
            'paid_at'               => $this->paid_at?->toISOString(),
            'plan_name'             => $this->whenLoaded(
                'subscription',
                fn () => $this->subscription?->plan?->name,
            ),
            'facility'              => $this->whenLoaded(
                'facility',
                fn () => [
                    'id'            => $this->facility?->id,
                    'facility_name' => $this->facility?->facility_name,
                    'facility_code' => $this->facility?->facility_code,
                ],
            ),
        ];
    }
}
