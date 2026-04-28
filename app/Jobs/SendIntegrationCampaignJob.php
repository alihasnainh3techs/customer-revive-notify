<?php

namespace App\Jobs;

use App\Models\Campaign;
use App\Models\CampaignLog;
use App\Models\Integration;
use App\Services\IntegrationService;
use App\Services\TemplateVariableService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class SendIntegrationCampaignJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        public Campaign    $campaign,
        public array       $customerData,
        public Integration $integration
    ) {
        //
    }

    public function handle(TemplateVariableService $variableService, IntegrationService $integrationService): void
    {
        $campaign    = $this->campaign;
        $customer    = $this->customerData;
        $integration = $this->integration->fresh();

        // Route to the correct provider handler
        match ($integration->provider) {
            'bsp'   => $this->handleBsp($campaign, $customer, $integration, $variableService, $integrationService),
            'texnity' => $this->handleTexnity($campaign, $customer, $integration, $variableService, $integrationService),
            'whatomation' => $this->handleWhatomation($campaign, $customer, $integration, $variableService, $integrationService),
            default => Log::info("SendIntegrationCampaignJob: provider [{$integration->provider}] not handled yet."),
        };
    }

    // -----------------------------------------------------------------
    // Whatomation — Whatsapp Message
    // -----------------------------------------------------------------
    private function handleWhatomation(
        Campaign             $campaign,
        array                $customer,
        Integration          $integration,
        TemplateVariableService $variableService,
        IntegrationService   $integrationService
    ): void {
        // 1. Skip silently if customer has no phone
        if (empty($customer['phone'])) {
            Log::info("SendIntegrationCampaignJob [Whatomation] skipped: customer [{$customer['id']}] has no phone number.");
            return;
        }

        // 2. Re-check integration is still active
        if (!$integration->status) {
            $reason = 'Whatomation integration is disabled';
            $this->logToDb($campaign, $customer, 'whatomation', 'failed', $reason);
            $this->fail($reason);
            return;
        }

        $template = DB::table('templates')->where('id', $campaign->message_template_id)->first();
        if (!$template) {
            $reason = "Message template [{$campaign->message_template_id}] not found";
            $this->logToDb($campaign, $customer, 'whatomation', 'failed', $reason);
            $this->fail($reason);
            return;
        }
        if (!$template->status) {
            $reason = "Message template [{$template->id}] is inactive";
            $this->logToDb($campaign, $customer, 'whatomation', 'failed', $reason);
            $this->fail($reason);
            return;
        }

        // 5. Build final message body
        $shopData  = $this->getShopData($campaign->user);
        $mapping   = $variableService->getMapping($campaign, $customer, $shopData);
        $finalBody = $variableService->replace($template->body, $mapping);

        // 6. Send Whatsapp message via Whatomation
        try {
            $result = $integrationService->sendWhatomationMessage(
                storeName: $campaign->user->name,
                customerId: $customer['id'],
                to: $customer['phone'],
                content: $finalBody,
            );
        } catch (\Throwable $e) {
            $reason = 'Whatomation API connection error: ' . $e->getMessage();
            Log::error("SendIntegrationCampaignJob [Whatomation] failed for [{$customer['phone']}]: {$reason}");
            $this->logToDb($campaign, $customer, 'whatomation', 'failed', $reason);
            $this->fail($e);
            return;
        }

        // 7. Check response — success only when status === true
        if (empty($result['status'])) {
            $reason = $result['message'] ?? 'Whatomation returned an unknown error';
            Log::error("SendIntegrationCampaignJob [Whatomation] failed for [{$customer['phone']}]: {$reason}");
            $this->logToDb($campaign, $customer, 'whatomation', 'failed', $reason);
            $this->fail($reason);
            return;
        }

        // 8. Log success
        $this->logToDb($campaign, $customer, 'whatomation', 'sent');
        Log::info("SendIntegrationCampaignJob [Whatomation]: Message sent to [{$customer['phone']}] for campaign [{$campaign->id}].");
    }

    // -----------------------------------------------------------------
    // Texnity — Whatsapp Message
    // -----------------------------------------------------------------
    private function handleTexnity(
        Campaign             $campaign,
        array                $customer,
        Integration          $integration,
        TemplateVariableService $variableService,
        IntegrationService   $integrationService
    ): void {}

    // -----------------------------------------------------------------
    // BSP — SMS
    // -----------------------------------------------------------------

    private function handleBsp(
        Campaign             $campaign,
        array                $customer,
        Integration          $integration,
        TemplateVariableService $variableService,
        IntegrationService   $integrationService
    ): void {
        // 1. Skip silently if customer has no phone
        if (empty($customer['phone'])) {
            Log::info("SendIntegrationCampaignJob [BSP] skipped: customer [{$customer['id']}] has no phone number.");
            return;
        }

        // 2. Re-check integration is still active
        if (!$integration->status) {
            $reason = 'BSP integration is disabled';
            $this->logToDb($campaign, $customer, 'sms', 'failed', $reason);
            $this->fail($reason);
            return;
        }

        // 3. Extract BSP credentials from configurations
        $config  = $integration->configurations;
        $email   = $config['email']   ?? null;
        $key     = $config['password'] ?? null;
        $maskId  = $config['mask_id']  ?? null;

        $template = DB::table('templates')->where('id', $campaign->message_template_id)->first();
        if (!$template) {
            $reason = "Message template [{$campaign->message_template_id}] not found";
            $this->logToDb($campaign, $customer, 'sms', 'failed', $reason);
            $this->fail($reason);
            return;
        }
        if (!$template->status) {
            $reason = "Message template [{$template->id}] is inactive";
            $this->logToDb($campaign, $customer, 'sms', 'failed', $reason);
            $this->fail($reason);
            return;
        }

        // 5. Build final message body
        $shopData  = $this->getShopData($campaign->user);
        $mapping   = $variableService->getMapping($campaign, $customer, $shopData);
        $finalBody = $variableService->replace($template->body, $mapping);

        // 6. Send SMS via BSP
        try {
            $result = $integrationService->sendSMS(
                email: $email,
                key: $key,
                mask: $maskId,
                to: $customer['phone'],
                message: $finalBody,
            );
        } catch (\Throwable $e) {
            $reason = 'BSP API connection error: ' . $e->getMessage();
            Log::error("SendIntegrationCampaignJob [BSP] failed for [{$customer['phone']}]: {$reason}");
            $this->logToDb($campaign, $customer, 'sms', 'failed', $reason);
            $this->fail($e);
            return;
        }

        // 7. Check response — success only when sms.code === "000"
        $code = $result['sms']['code'] ?? null;

        if ($code !== '000') {
            $reason = $result['sms']['response']
                ?? ($result['message'] ?? 'BSP returned an unknown error');
            Log::error("SendIntegrationCampaignJob [BSP] failed for [{$customer['phone']}]: {$reason}");
            $this->logToDb($campaign, $customer, 'sms', 'failed', $reason);
            $this->fail($reason);
            return;
        }

        // 8. Log success
        $this->logToDb($campaign, $customer, 'sms', 'sent');
        Log::info("SendIntegrationCampaignJob [BSP]: SMS sent to [{$customer['phone']}] for campaign [{$campaign->id}].");
    }

    // -----------------------------------------------------------------
    // DB logging
    // -----------------------------------------------------------------

    private function logToDb(
        Campaign $campaign,
        array    $customer,
        string   $channel,
        string   $status,
        ?string  $failureReason = null
    ): void {
        CampaignLog::create([
            'campaign_id'    => $campaign->id,
            'user_id'        => $campaign->user_id,
            'customer_id'    => $customer['id'],
            'customer_phone' => $customer['phone'],
            'customer_name'  => trim(($customer['firstName'] ?? '') . ' ' . ($customer['lastName'] ?? '')),
            'channel'        => $channel,
            'status'         => $status,
            'failure_reason' => $failureReason,
            'sent_at'        => $status === 'sent' ? now() : null,
        ]);
    }

    // -----------------------------------------------------------------
    // Shop data
    // -----------------------------------------------------------------

    private function getShopData($user): array
    {
        $response = $user->api()->graph('
            query {
                shop {
                    name
                    url
                    email
                }
            }
        ');

        $shop = $response['body']['data']['shop'] ?? null;

        if ($shop instanceof \Gnikyt\BasicShopifyAPI\ResponseAccess) {
            $shop = $shop->toArray();
        }

        return $shop ?? [];
    }
}
