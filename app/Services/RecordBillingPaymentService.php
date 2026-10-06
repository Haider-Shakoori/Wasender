<?php

namespace App\Services;

use App\Models\BillingPayment;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Facades\DB;

final class RecordBillingPaymentService
{
    public function __construct(private PlatformAuditService $audit) {}

    public function record(Tenant $tenant, array $data, User $actor): BillingPayment
    {
        return DB::transaction(function () use ($tenant, $data, $actor): BillingPayment {
            $subscription = $tenant->currentSubscription()->lockForUpdate()->first();
            $payment = BillingPayment::create([
                'tenant_id' => $tenant->id,
                'subscription_id' => $subscription?->id,
                'amount' => $data['amount'],
                'currency' => strtoupper($data['currency']),
                'payment_method' => 'manual',
                'reference' => $data['reference'] ?? null,
                'status' => 'paid',
                'paid_at' => $data['paid_at'],
                'recorded_by' => $actor->id,
                'notes' => $data['notes'] ?? null,
            ]);
            $this->audit->record('billing.payment.recorded', $actor, $tenant, [
                'payment_uuid' => $payment->uuid,
                'amount' => $payment->amount,
                'currency' => $payment->currency,
            ]);

            return $payment;
        }, 3);
    }
}
