<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

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

        $response = Http::withHeaders([
            'X-Timestamp' => $timestamp,
            'X-Signature' => $signature,
        ])->get("https://apps-integration.kiz.app/api/stores/{$storeName}/apps");

        $data = $response->json();

        // Log full response
        Log::info('Get Installed Apps API Response', [
            'status' => $response->status(),
            'body'   => $response->body(),
            'json'   => $data,
        ]);

        return $response->json();
    }

    public function validateAndFetchTemplates(string $shopName, string $apiKey): array
    {
        $response = Http::get(
            'https://shopify.texnity.com/api/get-texnity-templates',
            [
                // 'shop_name' => $shopName,
                'shop_name' => 'kyc-storee.myshopify.com',
                'apikey'    => $apiKey,
            ]
        );

        $data = $response->json();

        // Log full response
        Log::info('Texnity API Response', [
            'status' => $response->status(),
            'body'   => $response->body(),
            'json'   => $data,
        ]);

        return $response->json();
    }

    public function fetchIntegrationStatus(string $storeName, string $appSlug): array
    {
        $timestamp = time();
        $body = '';

        $signature = hash_hmac('sha256', $body, env('WON_CONNECTOR_KEY', 'WNOC_H3TECHS'));

        $response = Http::withHeaders([
            'X-App-Slug'  => $appSlug,
            'X-Timestamp' => $timestamp,
            'X-Signature' => $signature,
        ])->get("https://apps-integration.kiz.app/api/stores/{$storeName}/apps/{$appSlug}");

        $data = $response->json();

        // Log full response
        Log::info('Whatomation API Response', [
            'status' => $response->status(),
            'body'   => $response->body(),
            'json'   => $data,
        ]);

        return $response->json();
    }

    public function sendSMS(string $email, string $key, string $mask, string $to, string $message): array
    {
        $response = Http::asForm()->post(
            'https://secure.h3techs.com/sms/api/send',
            [
                'email'   => $email,
                'key'     => $key,
                'mask'    => $mask,
                'to'      => $to,
                'message' => $message,
            ]
        );

        $data = $response->json();

        // Log full response
        Log::info('Whatomation API Response', [
            'status' => $response->status(),
            'body'   => $response->body(),
            'json'   => $data,
        ]);

        return $response->json();
    }

    public function sendTexnityMessage(string $storeName, string $customerId, string $to, string $content,  string $apiKey)
    {
        $to = ltrim($to, '+');
        $customerId = preg_replace('/\D/', '', $customerId);

        $payload = [
            'shop_name' => $storeName,
            'event' => 'notification',
            'payload' => [
                'order_id' => $customerId, // customer id
                'sent_to' => $to,
                'content' => $content,
                'apikey' => $apiKey ?? null,
            ]
        ];

        $timestamp = time();
        $body = json_encode($payload, JSON_UNESCAPED_SLASHES);

        $signature = hash_hmac(
            'sha256',
            $body,
            env('TEXNITY_CONNECTOR_KEY', 'TEXNITY_H3TECHS')
        );

        $response = Http::withHeaders([
            'X-App-Slug' => 'texnity',
            'X-Timestamp' => (string)$timestamp,
            'X-Signature' => $signature,
            'Content-Type' => 'application/json',
        ])->post('https://apps-integration.kiz.app/api/relay', $payload);

        $data = $response->json();

        // Log full response
        Log::info('Send Message Texnity API Response', [
            'status' => $response->status(),
            'body'   => $response->body(),
            'json'   => $data,
        ]);

        return $response->json();
    }

    public function sendWhatomationMessage(string $storeName, string $customerId, string $to, string $content)
    {

        $to = ltrim($to, '+');
        $customerId = preg_replace('/\D/', '', $customerId);

        $payload = [
            'shop_name' => $storeName,
            'event' => 'notification',
            'payload' => [
                'order_id' => $customerId, // customer id
                'sent_to' => $to,
                'content' => $content,
                'apikey' => null,
            ]
        ];

        $timestamp = time();
        $body = json_encode($payload, JSON_UNESCAPED_SLASHES);

        $signature = hash_hmac(
            'sha256',
            $body,
            env('WON_CONNECTOR_KEY', 'WNOC_H3TECHS')
        );

        $response = Http::withHeaders([
            'X-App-Slug' => 'whatomation',
            'X-Timestamp' => (string)$timestamp,
            'X-Signature' => $signature,
            'Content-Type' => 'application/json',
        ])->post('https://apps-integration.kiz.app/api/relay', $payload);

        $data = $response->json();

        // Log full response
        Log::info('Send Whatomation API Response', [
            'status' => $response->status(),
            'body'   => $response->body(),
            'json'   => $data,
        ]);

        return $response->json();
    }
}
