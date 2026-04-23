<?php

namespace App\Jobs;

use App\Models\Campaign;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class SendEmailJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(public Campaign $campaign)
    {
        //
    }

    public function handle(): void
    {
        // TODO: implement email sending via SMTP configuration
        Log::info("SendEmailJob fired for campaign [{$this->campaign->id}] '{$this->campaign->campaign_name}'", [
            'smtp_service'   => $this->campaign->user->smtpConfiguration->service ?? null,
            'from_email'     => $this->campaign->user->smtpConfiguration->custom_from_email ?? null,
        ]);
    }
}
