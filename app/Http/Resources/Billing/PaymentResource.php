<?php

declare(strict_types=1);

namespace App\Http\Resources\Billing;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PaymentResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'                    => $this->id,
            'facility_id'           => $this->facility_id,
            'subscription_id'       => $this->subscription_id,
            'amount'                => (float) $this->amount,
            'currency'              => $this->currency,
            'method'                => $this->method->value,
            'method_label'          => $this->method->label(),
            'payment_type'          => $this->payment_type->value,
            'payment_type_label'    => $this->payment_type->label(),
            'status'                => $this->status->value,
            'status_label'          => $this->status->label(),
            'transaction_reference' => $this->transaction_reference,
            'receipt_url'           => $this->receiptUrl(),
            'receipt_download_url'  => $request->routeIs('admin.billing.*')
                ? $this->adminReceiptDownloadUrl()
                : $this->receiptDownloadUrl(),
            'receipt_path'          => $this->receipt_path,
            'receipt_notes'         => $this->receipt_notes,
            'paid_at'               => $this->paid_at?->toISOString(),
            'approved_at'           => $this->approved_at?->toISOString(),
            'approved_by'           => $this->whenLoaded('approvedBy', function () {
                return [
                    'user_id' => $this->approvedBy?->id,
                ];
            }),
            'rejection_reason'      => $this->rejection_reason,

            // ── Gateway fields (null until integrated) ────────────────────
            'gateway_name'          => $this->gateway_name,
            'gateway_transaction_id' => $this->gateway_transaction_id,

            'created_at'            => $this->created_at?->toISOString(),
        ];
    }
}
