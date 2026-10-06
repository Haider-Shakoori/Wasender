<?php

namespace App\Data\Campaigns;

final readonly class AudienceCursor
{
    public function __construct(public int $afterContactId = 0) {}
}
