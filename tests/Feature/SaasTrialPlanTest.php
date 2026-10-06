<?php

namespace Tests\Feature;

use App\Models\SubscriptionPlan;
use Database\Seeders\SubscriptionPlanSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class SaasTrialPlanTest extends TestCase
{
    use RefreshDatabase;

    public function test_trial_plan_allows_a_real_whatsapp_workflow(): void
    {
        $this->seed(SubscriptionPlanSeeder::class);

        $trial = SubscriptionPlan::query()->where('slug', 'trial')->with('features')->firstOrFail();

        $this->assertSame(14, $trial->trial_days);
        $this->assertTrue((bool) $trial->features->firstWhere('feature_key', 'whatsapp.sessions')?->boolean_value);
        $this->assertTrue((bool) $trial->features->firstWhere('feature_key', 'messages.send')?->boolean_value);
        $this->assertTrue((bool) $trial->features->firstWhere('feature_key', 'inbox.access')?->boolean_value);
        $this->assertSame(1, $trial->features->firstWhere('feature_key', 'whatsapp_sessions.max')?->integer_value);
        $this->assertSame(250, $trial->features->firstWhere('feature_key', 'messages.monthly')?->integer_value);
    }
}
