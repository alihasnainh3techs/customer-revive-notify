<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Campaign;
use App\Services\ShopifyDiscountService;
use App\Jobs\SendWhatsAppMessageJob;
use App\Jobs\SendEmailJob;
use Illuminate\Support\Facades\Log;

class ScheduleCampaigns extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:schedule-campaigns';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Process active campaigns and create Shopify discount codes on their scheduled dates';

    public function __construct(private ShopifyDiscountService $discountService)
    {
        parent::__construct();
    }

    /**
     * Execute the console command.
     */
    public function handle(): void
    {
        $today        = now();
        $currentDay   = $today->day;
        $currentMonth = $today->month;

        // ---------------------------------------------------------------
        // Build the base query — custom campaigns starting today
        // ---------------------------------------------------------------
        $query = Campaign::with(['user.device', 'user.smtpConfiguration'])
            ->where('campaign_status', 'active')
            ->where(function ($q) use ($today, $currentDay, $currentMonth) {

                // Custom schedule: runs on the exact custom_start_date
                $q->where(function ($inner) use ($today) {
                    $inner->where('schedule_type', 'custom')
                        ->whereDate('custom_start_date', $today);
                });

                // Monthly schedule: runs on the 1st of each month
                // monthly_frequency = N means the first N months of the year (Jan–MonthN)
                // So it fires when today is the 1st AND current month <= monthly_frequency
                if ($currentDay === 1) {
                    $q->orWhere(function ($inner) use ($currentMonth) {
                        $inner->where('schedule_type', 'monthly')
                            ->whereNotNull('monthly_frequency')
                            ->where('monthly_frequency', '>=', $currentMonth);
                    });
                }
            });

        $campaigns = $query->get();

        if ($campaigns->isEmpty()) {
            Log::info('ScheduleCampaigns: no campaigns matched for today.');
            return;
        }

        Log::info("ScheduleCampaigns: {$campaigns->count()} campaign(s) matched.");

        foreach ($campaigns as $campaign) {
            $logContext = $campaign->load('user.device', 'user.smtpConfiguration')->toArray();
            Log::info('Campaign matched', $logContext);

            try {
                $this->processCampaign($campaign);
            } catch (\Throwable $e) {
                Log::error("Campaign [{$campaign->id}] failed: {$e->getMessage()}", [
                    'exception' => $e,
                ]);
                // Continue processing remaining campaigns even if one fails
            }
        }
    }

    // ---------------------------------------------------------------
    // Per-campaign dispatch
    // ---------------------------------------------------------------

    private function processCampaign(Campaign $campaign): void
    {
        match ($campaign->campaign_type) {
            'discount' => $this->handleDiscountCampaign($campaign),
            default    => Log::info("Campaign [{$campaign->id}] type '{$campaign->campaign_type}' — no handler yet."),
        };
    }

    // ---------------------------------------------------------------
    // Discount campaign
    // ---------------------------------------------------------------

    private function handleDiscountCampaign(Campaign $campaign): void
    {
        Log::info("Campaign [{$campaign->id}] — syncing Shopify discount ({$campaign->discount_type})");

        try {
            $this->discountService->syncDiscount($campaign);
        } catch (\App\Exceptions\NoCustomersMatchedException $e) {
            // No matching customers — skip silently, already logged in service
            $this->warn("Skipped [{$campaign->id}]: {$e->getMessage()}");
            return;
        }

        $this->dispatchNotificationJobs($campaign);
    }

    // ---------------------------------------------------------------
    // Notification jobs
    // ---------------------------------------------------------------

    private function dispatchNotificationJobs(Campaign $campaign): void
    {
        $device            = $campaign->user->device;
        $smtpConfiguration = $campaign->user->smtpConfiguration;

        // Dispatch WhatsApp job only if device exists and enable_whatsapp is true
        if ($device && $device->enable_whatsapp) {
            SendWhatsAppMessageJob::dispatch($campaign);
            Log::info("Campaign [{$campaign->id}] — SendWhatsAppMessageJob dispatched.");
        } else {
            Log::info("Campaign [{$campaign->id}] — WhatsApp skipped (device missing or enable_whatsapp is false).");
        }

        // Dispatch email job only if SMTP configuration exists and status is true
        if ($smtpConfiguration && $smtpConfiguration->status) {
            SendEmailJob::dispatch($campaign);
            Log::info("Campaign [{$campaign->id}] — SendEmailJob dispatched.");
        } else {
            Log::info("Campaign [{$campaign->id}] — Email skipped (SMTP configuration missing or status is false).");
        }
    }
}
