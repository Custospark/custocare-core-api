<?php

declare(strict_types=1);

namespace App\Services\Billing\Contracts;

use App\Models\Payment;
use App\Models\Subscription;
use App\Models\User;
use Illuminate\Http\UploadedFile;

interface PaymentServiceInterface
{
    public function recordPayment(Subscription $subscription, array $data, ?UploadedFile $receipt = null): Payment;
    public function approvePayment(Payment $payment, User $approvedBy, ?string $notes = null): Payment;
    public function rejectPayment(Payment $payment, User $rejectedBy, string $reason): Payment;
    public function getPaymentsForFacility(int $facilityId, array $filters = [], int $perPage = 15): LengthAwarePaginator;
    public function getAllPayments(array $filters = [], int $perPage = 15): LengthAwarePaginator;
    public function findPaymentById(int $id): ?Payment;
}
