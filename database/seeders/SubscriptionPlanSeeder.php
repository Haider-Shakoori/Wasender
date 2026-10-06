<?php

namespace Database\Seeders;

use App\Enums\BillingInterval;
use App\Enums\PlanFeatureValueType;
use App\Enums\SubscriptionPlanStatus;
use App\Models\SubscriptionPlan;
use Illuminate\Database\Seeder;

final class SubscriptionPlanSeeder extends Seeder
{
    public function run(): void
    {
        $plans = [
            'trial' => ['Trial', 'Explore the core workspace with practical starter limits.', true, true, 14, ['dashboard.access', 'team.manage', 'roles.manage', 'audit_logs.view'], ['team_members.max' => 5, 'pending_invitations.max' => 5, 'roles.custom.max' => 3]],
            'starter' => ['Starter', 'Essential controls for a small operational team.', true, false, 0, ['dashboard.access', 'team.manage', 'roles.manage', 'audit_logs.view'], ['team_members.max' => 10, 'pending_invitations.max' => 10, 'roles.custom.max' => 5]],
            'growth' => ['Growth', 'Broader collaboration and integration entitlements.', true, true, 0, ['dashboard.access', 'team.manage', 'roles.manage', 'audit_logs.view', 'whatsapp.sessions', 'messages.send', 'contacts.manage', 'campaigns.manage', 'campaigns.schedule', 'whatsapp_message_templates', 'api_keys.manage'], ['team_members.max' => 30, 'pending_invitations.max' => 30, 'roles.custom.max' => 15, 'whatsapp_sessions.max' => 3, 'messages.monthly' => 1000, 'contacts.max' => 10000, 'contact_groups.max' => 100, 'contact_labels.max' => 100, 'contact_segments.max' => 50, 'campaigns.max' => 100, 'campaigns.active_max' => 25, 'campaigns.monthly_max' => 100, 'campaigns.recipients_per_campaign_max' => 5000, 'campaigns.manual_contacts_max' => 500, 'campaigns.sessions_per_campaign_max' => 3, 'whatsapp_message_templates.max' => 100, 'whatsapp_message_templates.published_max' => 50]],
            'business' => ['Business', 'Flexible controls and unlimited current workspace resources.', true, false, 0, array_keys(config('subscriptions.features')), ['team_members.max' => null, 'pending_invitations.max' => null, 'roles.custom.max' => null, 'whatsapp_sessions.max' => null, 'messages.monthly' => null, 'contacts.max' => null, 'contact_groups.max' => null, 'contact_labels.max' => null, 'contact_segments.max' => null, 'campaigns.max' => null, 'campaigns.active_max' => null, 'campaigns.monthly_max' => null, 'campaigns.recipients_per_campaign_max' => null, 'campaigns.manual_contacts_max' => null, 'campaigns.sessions_per_campaign_max' => null, 'whatsapp_message_templates.max' => null, 'whatsapp_message_templates.published_max' => null]],
        ];
        foreach ($plans as $order => $definition) {
            [$name,$description,$public,$featured,$trialDays,$features,$limits] = $definition;
            $slug = is_string($order) ? $order : strtolower($name);
            $plan = SubscriptionPlan::updateOrCreate(['slug' => $slug], ['name' => $name, 'description' => $description, 'status' => SubscriptionPlanStatus::Active, 'is_public' => $public, 'is_featured' => $featured, 'sort_order' => array_search($slug, array_keys($plans), true), 'billing_interval' => BillingInterval::Monthly, 'price_amount' => null, 'price_currency' => 'USD', 'trial_days' => $trialDays, 'grace_days' => 3, 'is_system' => true]);
            $rows = [];
            foreach (config('subscriptions.features') as $key => $label) {
                $rows[$key] = ['value_type' => PlanFeatureValueType::Boolean, 'boolean_value' => in_array($key, $features, true) || ($key === 'automations.access' && in_array($slug, ['growth', 'business'], true)), 'integer_value' => null, 'is_unlimited' => false];
            }
            $limits['automations.max'] ??= $slug === 'business' ? null : ($slug === 'growth' ? 100 : 0);
            foreach ($limits as $key => $value) {
                $rows[$key] = ['value_type' => PlanFeatureValueType::Integer, 'boolean_value' => null, 'integer_value' => $value, 'is_unlimited' => $value === null];
            }foreach ($rows as $key => $row) {
                $plan->features()->updateOrCreate(['feature_key' => $key], $row);
            }
        }
    }
}
