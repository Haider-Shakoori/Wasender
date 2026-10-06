<?php

namespace App\Http\Controllers;

use App\Models\PaymentGatewayConfig;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

final class PlatformPaymentGatewayController extends Controller
{
    public function index(): View
    {
        $gateways = PaymentGatewayConfig::query()->orderByDesc('is_default')->orderBy('provider')->get()->keyBy('provider');

        return view('platform.payment-gateways.index', compact('gateways'));
    }

    public function update(Request $request, string $provider): RedirectResponse
    {
        abort_unless(in_array($provider, ['stripe'], true), 404);

        $data = $request->validate([
            'is_enabled' => ['nullable', 'boolean'],
            'is_default' => ['nullable', 'boolean'],
            'mode' => ['required', 'in:test,live'],
            'publishable_key' => ['nullable', 'string', 'max:255'],
            'secret_key' => ['nullable', 'string', 'max:255'],
            'webhook_secret' => ['nullable', 'string', 'max:255'],
        ]);

        DB::transaction(function () use ($provider, $data): void {
            $gateway = PaymentGatewayConfig::query()->firstOrNew(['provider' => $provider]);
            $credentials = (array) ($gateway->credentials_encrypted ?? []);

            foreach (['publishable_key', 'secret_key', 'webhook_secret'] as $key) {
                if (filled($data[$key] ?? null)) {
                    $credentials[$key] = trim($data[$key]);
                }
            }

            if ((bool) ($data['is_default'] ?? false)) {
                PaymentGatewayConfig::query()->where('provider', '!=', $provider)->update(['is_default' => false]);
            }

            $gateway->fill([
                'is_enabled' => (bool) ($data['is_enabled'] ?? false),
                'is_default' => (bool) ($data['is_default'] ?? false),
                'mode' => $data['mode'],
                'configuration' => [],
                'credentials_encrypted' => $credentials,
            ])->save();
        }, 3);

        return back()->with('status', ucfirst($provider).' gateway settings updated.');
    }
}
