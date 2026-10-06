<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;

final class FailedJobService
{
    public function page(): LengthAwarePaginator
    {
        return DB::table('failed_jobs')->select(['id', 'uuid', 'connection', 'queue', 'failed_at'])->latest('failed_at')->paginate(20);
    }

    public function summary(string $uuid): object
    {
        return DB::table('failed_jobs')->select(['id', 'uuid', 'connection', 'queue', 'failed_at'])->where('uuid', $uuid)->firstOrFail();
    }

    public function retry(string $uuid, User $actor, PlatformAuditService $audit): void
    {
        $job = $this->summary($uuid);
        Artisan::call('queue:retry', ['id' => [$uuid]]);
        $audit->record('queue.failed_job.retried', $actor, null, ['job_uuid' => $job->uuid, 'queue' => $job->queue]);
    }

    public function delete(string $uuid, User $actor, PlatformAuditService $audit): void
    {
        $job = $this->summary($uuid);
        DB::table('failed_jobs')->where('uuid', $uuid)->delete();
        $audit->record('queue.failed_job.deleted', $actor, null, ['job_uuid' => $job->uuid, 'queue' => $job->queue]);
    }
}
