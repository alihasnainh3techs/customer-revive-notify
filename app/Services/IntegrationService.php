<?php

namespace App\Services;


class IntegrationService
{
    public function getApps(string $storeName)
    {
        $timestamp = time();
        $body = '';

        $signature = hash_hmac(
            'sha256',
            $body,
            env('CONNECTOR_KEY', 'H3TECHS')
        );

        $response = \Illuminate\Support\Facades\Http::withHeaders([
            'X-Timestamp' => $timestamp,
            'X-Signature' => $signature,
        ])->get("https://apps-integration.kiz.app/api/stores/{$storeName}/apps");

        return $response->json();
    }
}
