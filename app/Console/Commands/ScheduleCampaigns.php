<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Campaign;
use App\Services\ShopifyDiscountService;
use App\Services\ShopifyCustomerService;
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

    public function __construct(
        private ShopifyDiscountService  $discountService,
        private ShopifyCustomerService  $customerService,
    ) {
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
            'other'  => $this->handleDefaultCampaign($campaign),
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

        $shouldSendWhatsApp = $device && $device->enable_whatsapp;
        $shouldSendEmail    = $smtpConfiguration && $smtpConfiguration->status;

        // Nothing to send — skip customer fetch entirely
        if (!$shouldSendWhatsApp && !$shouldSendEmail) {
            Log::info("Campaign [{$campaign->id}] — no notification channels active, skipping.");
            return;
        }

        // Fetch all matching customers with full data
        $customers = $this->customerService->fetchCampaignCustomers($campaign);

        if (empty($customers)) {
            Log::info("Campaign [{$campaign->id}] — no customers found, no jobs dispatched.");
            return;
        }

        $emailCount    = 0;
        $whatsappCount = 0;

        foreach ($customers as $customer) {
            if ($shouldSendEmail) {
                SendEmailJob::dispatch($campaign, $customer);
                $emailCount++;
            }

            if ($shouldSendWhatsApp) {
                SendWhatsAppMessageJob::dispatch($campaign, $customer);
                $whatsappCount++;
            }
        }

        Log::info("Campaign [{$campaign->id}] — jobs dispatched.", [
            'email_jobs'    => $emailCount,
            'whatsapp_jobs' => $whatsappCount,
        ]);
    }

    private function handleDefaultCampaign(Campaign $campaign): void
    {
        Log::info("Campaign [{$campaign->id}] — Processing notification-only campaign.");

        // Skip syncDiscount and go straight to dispatching
        $this->dispatchNotificationJobs($campaign);
    }
}
