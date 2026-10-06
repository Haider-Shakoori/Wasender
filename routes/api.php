<?php

use App\Http\Controllers\IntegrationApiController;
use Illuminate\Support\Facades\Route;

Route::prefix('integrations/{integration}')->middleware(['integration.auth', 'tenant.subscription', 'tenant.feature:integrations.access'])->group(function (): void {
    Route::post('events', [IntegrationApiController::class, 'event'])->middleware('throttle:integration-events')->name('api.integrations.events');
    Route::post('messages', [IntegrationApiController::class, 'message'])->middleware('throttle:integration-messages')->name('api.integrations.messages');
});
