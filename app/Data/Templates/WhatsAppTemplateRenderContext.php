<?php

namespace App\Data\Templates;

use App\Enums\TemplateVariableContext;
use Carbon\CarbonImmutable;
use DateTimeInterface;
use InvalidArgumentException;

final readonly class WhatsAppTemplateRenderContext
{
    public function __construct(public TemplateVariableContext $context, public array $values, public string $timezone = 'UTC', public ?CarbonImmutable $now = null)
    {
        try {
            new \DateTimeZone($timezone);
        } catch (\Throwable) {
            throw new InvalidArgumentException('Invalid render timezone.');
        }
        foreach ($values as $key => $value) {
            if (! is_string($key) || (! is_scalar($value) && $value !== null && ! $value instanceof DateTimeInterface)) {
                throw new InvalidArgumentException('Render values must be named scalars, null, or dates.');
            }
        }
    }
}
