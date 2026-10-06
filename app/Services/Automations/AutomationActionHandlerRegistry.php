<?php

namespace App\Services\Automations;

use App\Contracts\AutomationActionHandler;
use App\Enums\AutomationActionType;
use LogicException;

final class AutomationActionHandlerRegistry
{
    private array $handlers = [];

    public function __construct(SendWhatsAppTemplateAutomationActionHandler $send, AddContactLabelAutomationActionHandler $addLabel, RemoveContactLabelAutomationActionHandler $removeLabel, AddContactToGroupAutomationActionHandler $addGroup, RemoveContactFromGroupAutomationActionHandler $removeGroup, UpdateContactAutomationActionHandler $update, StopWorkflowAutomationActionHandler $stop)
    {
        foreach ([$send, $addLabel, $removeLabel, $addGroup, $removeGroup, $update, $stop] as $handler) {
            $this->register($handler);
        }
    }

    private function register(AutomationActionHandler $handler): void
    {
        foreach (AutomationActionType::cases() as $type) {
            if ($handler->supports($type)) {
                if (isset($this->handlers[$type->value])) {
                    throw new LogicException('Duplicate automation action handler.');
                }$this->handlers[$type->value] = $handler;
            }
        }
    }

    public function has(AutomationActionType|string $type): bool
    {
        return isset($this->handlers[$type instanceof AutomationActionType ? $type->value : $type]);
    }

    public function resolve(AutomationActionType|string $type): AutomationActionHandler
    {
        $key = $type instanceof AutomationActionType ? $type->value : $type;

        return $this->handlers[$key] ?? throw new LogicException('action_handler_unavailable');
    }
}
