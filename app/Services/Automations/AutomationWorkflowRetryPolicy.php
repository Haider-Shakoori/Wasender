<?php

namespace App\Services\Automations;

use App\Data\Automations\AutomationRetryDecision;
use App\Enums\AutomationWorkflowFailureClass;
use Carbon\CarbonImmutable;

final class AutomationWorkflowRetryPolicy
{
    public function decide(AutomationWorkflowFailureClass $class, int $attempt): AutomationRetryDecision
    {
        $max = config('automations.execution.step_max_attempts');
        if ($class !== AutomationWorkflowFailureClass::Transient || $attempt >= $max) {
            return new AutomationRetryDecision(false, null, $attempt >= $max ? 'attempt_limit_reached' : 'permanent_failure');
        } $seconds = min(config('automations.execution.retry_base_seconds') * (2 ** max(0, $attempt - 1)), config('automations.execution.retry_max_seconds'));

        return new AutomationRetryDecision(true, CarbonImmutable::now()->addSeconds($seconds), 'transient_failure');
    }
}
