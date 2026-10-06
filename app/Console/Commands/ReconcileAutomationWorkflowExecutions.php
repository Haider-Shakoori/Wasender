<?php

namespace App\Console\Commands;

use App\Services\Automations\ReconcileAutomationWorkflowExecutionService;
use Illuminate\Console\Command;

final class ReconcileAutomationWorkflowExecutions extends Command
{
    protected $signature = 'automations:reconcile-executions {--limit=100}';

    protected $description = 'Reconcile bounded stale or due automation executions';

    public function handle(ReconcileAutomationWorkflowExecutionService $service): int
    {
        $this->info('Reconciled '.$service->reconcile((int) $this->option('limit')).' execution(s).');

        return self::SUCCESS;
    }
}
