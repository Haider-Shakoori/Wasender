<?php

namespace Tests\Feature;

use App\Contracts\BillingGateway;
use App\Models\SubscriptionPlan;
use App\Models\TenantSubscription;
use Tests\TestCase;

final class TenantSelectedPlanCheckoutTest extends TestCase
{
    public function test_billing_gateway_contract_accepts_selected_plan(): void
    {
        $method = new \ReflectionMethod(BillingGateway::class, 'createCheckout');
        $parameters = $method->getParameters();

        $this->assertCount(4, $parameters);
        $this->assertSame(TenantSubscription::class, (string) $parameters[0]->getType());
        $this->assertSame(SubscriptionPlan::class, (string) $parameters[1]->getType());
    }
}
