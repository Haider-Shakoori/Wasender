<?php

namespace App\Http\Controllers;

use App\Services\FailedJobService;
use App\Services\PlatformAuditService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

final class PlatformQueueController extends Controller
{
    public function index(FailedJobService $jobs): View
    {
        return view('platform.queues.index', ['failedJobs' => $jobs->page()]);
    }

    public function show(string $uuid, FailedJobService $jobs): View
    {
        return view('platform.queues.show', ['job' => $jobs->summary($uuid)]);
    }

    public function retry(Request $r, string $uuid, FailedJobService $jobs, PlatformAuditService $audit): RedirectResponse
    {
        $jobs->retry($uuid, $r->user(), $audit);

        return redirect()->route('platform.queues.index')->with('status', 'Failed job released for retry.');
    }

    public function destroy(Request $r, string $uuid, FailedJobService $jobs, PlatformAuditService $audit): RedirectResponse
    {
        $jobs->delete($uuid, $r->user(), $audit);

        return redirect()->route('platform.queues.index')->with('status', 'Failed job deleted.');
    }
}
