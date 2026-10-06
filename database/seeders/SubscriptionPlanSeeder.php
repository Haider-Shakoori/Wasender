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
            'trial' => ['Trial', 'Try the real WhatsApp workflow before subscribing.', true, true, 14, ['dashboard.access', 'team.manage', 'roles.manage', 'audit_logs.view', 'whatsapp.sessions', 'messages.send', 'contacts.manage', 'campaigns.manage', 'whatsapp_message_templates', 'inbox.access', 'analytics.access', 'automations.access', 'chatbots.access', 'integrations.access'], ['team_members.max' => 2, 'pending_invitations.max' => 2, 'roles.custom.max' => 1, 'whatsapp_sessions.max' => 1, 'messages.monthly' => 250, 'contacts.max' => 500, 'contact_groups.max' => 10, 'contact_labels.max' => 20, 'contact_segments.max' => 10, 'campaigns.max' => 5, 'campaigns.active_max' => 2, 'campaigns.monthly_max' => 5, 'campaigns.recipients_per_campaign_max' => 100, 'campaigns.manual_contacts_max' => 100, 'campaigns.sessions_per_campaign_max' => 1, 'whatsapp_message_templates.max' => 10, 'whatsapp_message_templates.published_max' => 5, 'automations.max' => 3, 'chatbots.max' => 1, 'integrations.max' => 1]],
            'starter' => ['Starter', 'Essential WhatsApp messaging for a small team.', true, false, 0, ['dashboard.access', 'team.manage', 'roles.manage', 'audit_logs.view', 'whatsapp.sessions', 'messages.send', 'contacts.manage', 'campaigns.manage', 'whatsapp_message_templates', 'inbox.access', 'analytics.access'], ['team_members.max' => 5, 'pending_invitations.max' => 5, 'roles.custom.max' => 3, 'whatsapp_sessions.max' => 1, 'messages.monthly' => 2500, 'contacts.max' => 5000, 'contact_groups.max' => 50, 'contact_labels.max' => 50, 'contact_segments.max' => 25, 'campaigns.max' => 25, 'campaigns.active_max' => 5, 'campaigns.monthly_max' => 25, 'campaigns.recipients_per_campaign_max' => 1000, 'campaigns.manual_contacts_max' => 250, 'campaigns.sessions_per_campaign_max' => 1, 'whatsapp_message_templates.max' => 50, 'whatsapp_message_templates.published_max' => 25, 'automations.max' => 0, 'chatbots.max' => 0, 'integrations.max' => 0]],
            'growth' => ['Growth', 'Campaigns, automations and integrations for growing teams.', true, true, 0, ['dashboard.access', 'team.manage', 'roles.manage', 'audit_logs.view', 'whatsapp.sessions', 'messages.send', 'contacts.manage', 'campaigns.manage', 'campaigns.schedule', 'whatsapp_message_templates', 'inbox.access', 'analytics.access', 'automations.access', 'chatbots.access', 'integrations.access', 'api_keys.manage', 'webhooks.manage'], ['team_members.max' => 15, 'pending_invitations.max' => 15, 'roles.custom.max' => 10, 'whatsapp_sessions.max' => 3, 'messages.monthly' => 15000, 'contacts.max' => 25000, 'contact_groups.max' => 100, 'contact_labels.max' => 100, 'contact_segments.max' => 50, 'campaigns.max' => 100, 'campaigns.active_max' => 25, 'campaigns.monthly_max' => 100, 'campaigns.recipients_per_campaign_max' => 5000, 'campaigns.manual_contacts_max' => 500, 'campaigns.sessions_per_campaign_max' => 3, 'whatsapp_message_templates.max' => 100, 'whatsapp_message_templates.published_max' => 50, 'automations.max' => 100, 'chatbots.max' => 10, 'integrations.max' => 10, 'api_keys.max' => 10, 'webhooks.max' => 20]],
            'business' => ['Business', 'High-capacity WhatsApp operations with all current SaaS features.', true, false, 0, array_keys(config('subscriptions.features')), ['team_members.max' => null, 'pending_invitations.max' => null, 'roles.custom.max' => null, 'whatsapp_sessions.max' => null, 'messages.monthly' => null, 'contacts.max' => null, 'contact_groups.max' => null, 'contact_labels.max' => null, 'contact_segments.max' => null, 'campaigns.max' => null, 'campaigns.active_max' => null, 'campaigns.monthly_max' => null, 'campaigns.recipients_per_campaign_max' => null, 'campaigns.manual_contacts_max' => null, 'campaigns.sessions_per_campaign_max' => null, 'whatsapp_message_templates.max' => null, 'whatsapp_message_templates.published_max' => null, 'automations.max' => null, 'chatbots.max' => null, 'integrations.max' => null, 'api_keys.max' => null, 'webhooks.max' => null]],
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
