<?php

declare(strict_types=1);

use App\Http\Controllers\HealthController;
use App\Http\Controllers\InternalWhatsAppCampaignAttachmentController;
use App\Http\Controllers\InternalWhatsAppMediaController;
use App\Http\Controllers\PublicInvitationController;
use App\Http\Controllers\WhatsAppCampaignConnectorEventController;
use App\Http\Controllers\WhatsAppConnectorCallbackController;
use App\Http\Controllers\WhatsAppInboundConnectorEventController;
use App\Http\Controllers\WhatsAppMessageCallbackController;
use Illuminate\Support\Facades\Route;

Route::view('/', 'welcome')->name('home');
Route::get('/health', HealthController::class)->middleware('throttle:60,1')->name('health');
Route::post('/internal/whatsapp/events', WhatsAppConnectorCallbackController::class)
    ->middleware(['whatsapp.signature', 'throttle:120,1'])
    ->name('internal.whatsapp.events');
Route::post('/internal/whatsapp/inbox-events', WhatsAppInboundConnectorEventController::class)
    ->middleware(['whatsapp.signature', 'throttle:240,1'])
    ->name('internal.whatsapp.inbox-events');
Route::post('/internal/whatsapp/message-events', WhatsAppMessageCallbackController::class)->middleware(['whatsapp.signature', 'throttle:240,1'])->name('internal.whatsapp.message-events');
Route::get('/internal/whatsapp/messages/{messageUuid}/media', InternalWhatsAppMediaController::class)->middleware(['whatsapp.signature', 'throttle:120,1'])->name('internal.whatsapp.media');
Route::post('/internal/whatsapp/campaign-events', WhatsAppCampaignConnectorEventController::class)
    ->middleware(['whatsapp.signature', 'throttle:240,1'])
    ->name('internal.whatsapp.campaign-events');
Route::get('/internal/whatsapp/campaign-attachments/{attachmentUuid}', InternalWhatsAppCampaignAttachmentController::class)
    ->middleware(['whatsapp.signature', 'throttle:120,1'])
    ->name('internal.whatsapp.campaign-attachment');

Route::get('/invitations/{invitationUuid}', [PublicInvitationController::class, 'show'])->name('invitations.show');
Route::post('/invitations/{invitationUuid}/accept', [PublicInvitationController::class, 'accept'])->middleware('auth')->name('invitations.accept');
Route::get('/invitations/{invitationUuid}/register', [PublicInvitationController::class, 'register'])->middleware('guest')->name('invitations.register');
Route::post('/invitations/{invitationUuid}/register', [PublicInvitationController::class, 'registerStore'])->middleware(['guest', 'throttle:5,1'])->name('invitations.register.store');

require __DIR__.'/auth.php';
require __DIR__.'/tenant.php';
require __DIR__.'/platform.php';
