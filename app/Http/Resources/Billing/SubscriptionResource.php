<?php

declare(strict_types=1);

namespace App\Http\Resources\Billing;

use App\Services\Billing\Contracts\SubscriptionScheduledChangeServiceInterface;
use App\Services\Billing\SubscriptionPaymentActionResolver;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SubscriptionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $scheduledChange = app(SubscriptionScheduledChangeServiceInterface::class)
            ->getPendingChange($this->resource);

        return [
            'id'                   => $this->id,

            // ── Facility reference ────────────────────────────────────────
            'facility'             => $this->whenLoaded(
                'facility',
                fn () => new BillingFacilitySummaryResource($this->facility),
            ),

            // ── Plan ──────────────────────────────────────────────────────
            'plan'                 => new PlanResource($this->whenLoaded('plan')),
            'effective_plan'       => new PlanResource($this->whenLoaded('plan')),

            // ── Scheduled changes & cancel-at-period-end ─────────────────
            'scheduled_change'     => $scheduledChange
                ? new SubscriptionScheduledChangeResource($scheduledChange)
                : null,
            'cancel_at_period_end' => $this->isCancelAtPeriodEnd(),
            'access_ends_at'       => $this->accessEndsAt()?->toISOString(),

            // ── Status ────────────────────────────────────────────────────
            'status'               => $this->status->value,
            'status_label'         => $this->status->label(),
            'has_access'           => $this->hasAccess(),

            'payment_action'       => app(SubscriptionPaymentActionResolver::class)
                ->resolve($this->resource),

            // ── Timeline ─────────────────────────────────────────────────
            'trial_ends_at'        => $this->trial_ends_at?->toISOString(),
            'starts_at'            => $this->starts_at?->toISOString(),
            'ends_at'              => $this->ends_at?->toISOString(),
            'next_billing_date'    => $this->next_billing_date?->toISOString(),
            'grace_period_ends_at' => $this->grace_period_ends_at?->toISOString(),
            'days_remaining'       => $this->daysRemaining(),

            // ── Deactivation ──────────────────────────────────────────────
            'suspended_at'         => $this->suspended_at?->toISOString(),
            'cancelled_at'         => $this->cancelled_at?->toISOString(),

            // ── Approval ─────────────────────────────────────────────────
            'approved_at'          => $this->approved_at?->toISOString(),
            'onboarding_fee_paid'  => $this->onboarding_fee_paid,

            // ── Payments ─────────────────────────────────────────────────
            'payments'             => PaymentResource::collection($this->whenLoaded('payments')),

            'pending_payments_count'  => $this->when(
                isset($this->pending_payments_count),
                (int) $this->pending_payments_count,
            ),
            'approved_payments_count' => $this->when(
                isset($this->approved_payments_count),
                (int) $this->approved_payments_count,
            ),

            'notes'                => $this->notes,
            'created_at'           => $this->created_at?->toISOString(),
            'updated_at'           => $this->updated_at?->toISOString(),
        ];
    }
}
