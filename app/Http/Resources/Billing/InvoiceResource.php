<?php

declare(strict_types=1);

namespace App\Http\Resources\Billing;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class InvoiceResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'             => $this->id,
            'facility_id'    => $this->facility_id,
            'subscription_id'=> $this->subscription_id,
            'invoice_number' => $this->invoice_number,
            'invoice_type'   => $this->invoice_type->value,
            'invoice_type_label' => $this->invoice_type->label(),
            'status'         => $this->status->value,
            'status_label'   => $this->status->label(),
            'amount'         => (float) $this->amount,
            'currency'       => $this->currency,
            'paid_amount'    => (float) $this->paid_amount,
            'balance_due'    => $this->balanceDue(),
            'description'    => $this->description,
            'line_items'     => $this->line_items,
            'issued_at'      => $this->issued_at?->toDateString(),
            'due_at'         => $this->due_at?->toDateString(),
            'paid_at'        => $this->paid_at?->toDateString(),
            'cancelled_at'   => $this->cancelled_at?->toISOString(),
            'subscription'   => $this->whenLoaded('subscription', function () {
                return [
                    'id'     => $this->subscription?->id,
                    'status' => $this->subscription?->status?->value,
                    'plan'   => $this->subscription?->plan ? [
                        'id'   => $this->subscription->plan->id,
                        'name' => $this->subscription->plan->name,
                        'slug' => $this->subscription->plan->slug,
                    ] : null,
                ];
            }),
            'facility' => $this->whenLoaded('facility', function () {
                return [
                    'id'            => $this->facility?->id,
                    'facility_name' => $this->facility?->facility_name,
                    'facility_code' => $this->facility?->facility_code,
                ];
            }),
            'created_at' => $this->created_at?->toISOString(),
        ];
    }
}
