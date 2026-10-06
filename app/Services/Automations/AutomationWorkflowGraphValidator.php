<?php

namespace App\Services\Automations;

use Illuminate\Support\Collection;

final class AutomationWorkflowGraphValidator
{
    public function validate(Collection $steps): array
    {
        if ($steps->isEmpty()) {
            return [$this->error('workflow_has_no_steps', 'steps', null, 'Workflow has no steps.')];
        }

        if ($steps->count() > config('automations.max_steps')) {
            return [$this->error('definition_too_large', 'steps', null, 'Workflow has too many steps.')];
        }

        $byKey = $steps->keyBy('step_key');
        if ($byKey->count() !== $steps->count()) {
            return [$this->error('duplicate_step_key', 'steps', null, 'Step keys must be unique.')];
        }

        $edges = [];
        $incoming = array_fill_keys($byKey->keys()->all(), 0);
        $errors = [];

        foreach ($steps as $step) {
            $configuration = $step->configuration ?? [];
            $targets = $step->step_type->value === 'branch'
                ? [$configuration['true_step_key'] ?? null, $configuration['false_step_key'] ?? null]
                : [$configuration['next_step_key'] ?? null];
            $edges[$step->step_key] = [];

            foreach (array_filter($targets, fn ($target) => is_string($target) && $target !== '') as $target) {
                if ($target === $step->step_key) {
                    $errors[] = $this->error('self_reference', 'configuration', $step->step_key, 'Step cannot reference itself.');

                    continue;
                }

                if (! $byKey->has($target)) {
                    $errors[] = $this->error('unknown_step_reference', 'configuration', $step->step_key, 'Referenced step does not exist.');

                    continue;
                }

                $edges[$step->step_key][] = $target;
                $incoming[$target]++;
            }
        }

        if ($errors !== []) {
            return $errors;
        }

        $entries = array_keys(array_filter($incoming, fn ($count) => $count === 0));
        if (count($entries) === 0) {
            $errors[] = $this->error('entry_step_missing', 'steps', null, 'Workflow entry step is missing.');
        } elseif (count($entries) > 1) {
            $errors[] = $this->error('multiple_entry_steps', 'steps', null, 'Workflow must have exactly one entry step.');
        }

        $state = [];
        $cycle = false;
        $depthExceeded = false;
        $dfs = function (string $key, int $depth) use (&$dfs, &$state, &$cycle, &$depthExceeded, $edges): void {
            if ($depth > config('automations.max_path_depth')) {
                $depthExceeded = true;

                return;
            }

            if (($state[$key] ?? 0) === 1) {
                $cycle = true;

                return;
            }

            if (($state[$key] ?? 0) === 2) {
                return;
            }

            $state[$key] = 1;
            foreach ($edges[$key] as $next) {
                $dfs($next, $depth + 1);
            }
            $state[$key] = 2;
        };

        foreach ($byKey->keys() as $key) {
            if (($state[$key] ?? 0) === 0) {
                $dfs($key, 1);
            }
        }

        if ($cycle) {
            $errors[] = $this->error('cycle_detected', 'steps', null, 'Workflow cycles are not supported.');
        }

        if ($depthExceeded) {
            $errors[] = $this->error('definition_too_large', 'steps', null, 'Workflow path is too deep.');
        }

        if (count($entries) === 1) {
            $reachable = [];
            $visit = function (string $key) use (&$visit, &$reachable, $edges): void {
                if (isset($reachable[$key])) {
                    return;
                }

                $reachable[$key] = true;
                foreach ($edges[$key] as $next) {
                    $visit($next);
                }
            };
            $visit($entries[0]);

            foreach ($byKey->keys() as $key) {
                if (! isset($reachable[$key])) {
                    $errors[] = $this->error('unreachable_step', 'steps', $key, 'Step is unreachable.');
                }
            }
        }

        return $errors;
    }

    private function error(string $code, string $field, ?string $stepKey, string $message): array
    {
        return ['code' => $code, 'field' => $field, 'step_key' => $stepKey, 'message' => $message];
    }
}
