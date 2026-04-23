<?php

namespace App\Jobs;

use App\Models\Campaign;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class SendWhatsAppMessageJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(public Campaign $campaign)
    {
        //
    }

    public function handle(): void
    {
        // TODO: implement WhatsApp message sending via BSP integration
        Log::info("SendWhatsAppMessageJob fired for campaign [{$this->campaign->id}] '{$this->campaign->campaign_name}'", [
            'device_id'    => $this->campaign->user->device->id ?? null,
            'session_id'   => $this->campaign->user->device->session_id ?? null,
        ]);
    }
}
