<?php

namespace App\Jobs;

use App\Models\CampaignLog;
use App\Models\Campaign;
use App\Services\TemplateVariableService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;

class SendWhatsAppMessageJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(public Campaign $campaign, public array $customerData)
    {
        //
    }

    public function handle(TemplateVariableService $variableService): void
    {
        $campaign = $this->campaign->fresh(['user.device']);
        $customer = $this->customerData;

        // 1. Skip silently if customer has no phone — not a failure
        if (empty($customer['phone'])) {
            Log::info("SendWhatsappMessageJob skipped: customer [{$customer['id']}] has no phone number.");
            return;
        }

        // 2. Re-check Device config inside the job (could have changed since dispatch)
        $device = $campaign->user->device;
        if (!$device) {
            $reason = 'Device not found';
            $this->logToDb($campaign, $customer, 'failed', $reason);
            $this->fail($reason);
            return;
        }

        if ($device->status === 'disconnected') {
            $reason = 'Device not connected';
            $this->logToDb($campaign, $customer, 'failed', $reason);
            $this->fail($reason);
            return;
        }

        // 3. Load and validate template
        $template = DB::table('templates')->where('id', $campaign->message_template_id)->first();
        if (!$template) {
            $reason = "Message template [{$campaign->email_template_id}] not found";
            $this->logToDb($campaign, $customer, 'failed', $reason);
            $this->fail($reason);
            return;
        }
        if (!$template->status) {
            $reason = "Message template [{$template->id}] is inactive";
            $this->logToDb($campaign, $customer, 'failed', $reason);
            $this->fail($reason);
            return;
        }

        // 4. Build final subject and body
        $shopData     = $this->getShopData($campaign->user);
        $mapping      = $variableService->getMapping($campaign, $customer, $shopData);
        $finalBody    = $variableService->replace($template->body, $mapping);

        try {
            $this->send($device, $customer['phone'], $finalBody);
        } catch (\Throwable $e) {
            $reason = $e->getMessage();
            Log::error("SendWhatsappMessageJob failed sending to [{$customer['phone']}] for campaign [{$campaign->id}]: {$reason}");
            $this->logToDb($campaign, $customer, 'failed', $reason);
            $this->fail($e);
            return;
        }

        // TODO: implement WhatsApp message sending via BSP integration
        Log::info("SendWhatsAppMessageJob fired for campaign [{$this->campaign->id}] '{$this->campaign->campaign_name}'", [
            'customer_id'  => $this->customerData['id'] ?? null,
            'phone'        => $this->customerData['phone'] ?? null,
            'device_id'    => $this->campaign->user->device->id ?? null,
            'session_id'   => $this->campaign->user->device->session_id ?? null,
        ]);
        // 6. Log success
        $this->logToDb($campaign, $customer, 'sent');
        Log::info("SendWhatsappMessageJob: whatsapp message sent to [{$customer['phone']}] for campaign [{$campaign->id}].");
    }

    // -----------------------------------------------------------------
    // Whatsapp notification sending
    // -----------------------------------------------------------------
    private function send(object $device, string $phone, string $body): void {}

    // -----------------------------------------------------------------
    // DB logging
    // -----------------------------------------------------------------

    private function logToDb(
        Campaign $campaign,
        array    $customer,
        string   $status,
        ?string  $failureReason = null
    ): void {
        CampaignLog::create([
            'campaign_id'    => $campaign->id,
            'user_id'        => $campaign->user_id,
            'customer_id'    => $customer['id'],
            'customer_email' => $customer['email'],
            'customer_name'  => trim(($customer['firstName'] ?? '') . ' ' . ($customer['lastName'] ?? '')),
            'channel'        => 'whatsapp',
            'status'         => $status,
            'failure_reason' => $failureReason,
            'sent_at'        => $status === 'sent' ? now() : null,
        ]);
    }

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

        // ResponseAccess object — cast to array
        if ($shop instanceof \Gnikyt\BasicShopifyAPI\ResponseAccess) {
            $shop = $shop->toArray();
        }

        return $shop ?? [];
    }
}
