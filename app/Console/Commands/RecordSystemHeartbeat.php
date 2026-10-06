<?php

namespace App\Console\Commands;

use App\Models\SystemHeartbeat;
use Illuminate\Console\Command;

final class RecordSystemHeartbeat extends Command
{
    protected $signature = 'system:heartbeat';

    protected $description = 'Record the scheduler heartbeat';

    public function handle(): int
    {
        SystemHeartbeat::query()->updateOrCreate(['key' => 'scheduler'], ['ran_at' => now(), 'metadata' => ['source' => 'scheduler']]);

        return self::SUCCESS;
    }
}
