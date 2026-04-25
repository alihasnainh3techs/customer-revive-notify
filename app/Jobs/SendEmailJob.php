<?php

namespace App\Jobs;

use App\Models\Campaign;
use App\Models\CampaignLog;
use App\Services\TemplateVariableService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Mail\Message;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Symfony\Component\Mailer\Transport\Smtp\EsmtpTransport;

class SendEmailJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(public Campaign $campaign, public array $customerData)
    {
        //
    }

    public function handle(TemplateVariableService $variableService): void
    {
        $campaign = $this->campaign->fresh(['user.smtpConfiguration']);
        $customer = $this->customerData;

        // 1. Skip silently if customer has no email — not a failure
        if (empty($customer['email'])) {
            Log::info("SendEmailJob skipped: customer [{$customer['id']}] has no email address.");
            return;
        }

        // 2. Re-check SMTP config inside the job (could have changed since dispatch)
        $smtp = $campaign->user->smtpConfiguration;
        if (!$smtp || !$smtp->status) {
            $reason = !$smtp ? 'SMTP configuration missing' : 'SMTP configuration disabled';
            $this->logToDb($campaign, $customer, 'failed', $reason);
            $this->fail($reason);
            return;
        }

        // 3. Load and validate template
        $template = DB::table('templates')->where('id', $campaign->email_template_id)->first();
        if (!$template) {
            $reason = "Email template [{$campaign->email_template_id}] not found";
            $this->logToDb($campaign, $customer, 'failed', $reason);
            $this->fail($reason);
            return;
        }
        if (!$template->status) {
            $reason = "Email template [{$template->id}] is inactive";
            $this->logToDb($campaign, $customer, 'failed', $reason);
            $this->fail($reason);
            return;
        }

        // 4. Build final subject and body
        $shopData     = $this->getShopData($campaign->user);
        $mapping      = $variableService->getMapping($campaign, $customer, $shopData);
        $finalSubject = $variableService->replace($template->subject, $mapping);
        $finalBody    = $variableService->replace($template->body, $mapping);

        // 5. Send email
        try {
            $this->send($smtp, $customer['email'], $finalSubject, $finalBody);
        } catch (\Throwable $e) {
            $reason = $e->getMessage();
            Log::error("SendEmailJob failed sending to [{$customer['email']}] for campaign [{$campaign->id}]: {$reason}");
            $this->logToDb($campaign, $customer, 'failed', $reason);
            $this->fail($e);
            return;
        }

        // 6. Log success
        $this->logToDb($campaign, $customer, 'sent');
        Log::info("SendEmailJob: email sent to [{$customer['email']}] for campaign [{$campaign->id}].");
    }

    // -----------------------------------------------------------------
    // Email sending
    // -----------------------------------------------------------------

    /**
     * Route to SendGrid (default) or custom SMTP based on service type.
     */
    private function send(object $smtp, string $toEmail, string $subject, string $body): void
    {
        $settings = $smtp->service === 'default'
            ? $this->sendgridSettings()
            : $this->customSmtpSettings($smtp);

        $fromEmail = config('mail.from.address');

        $fromName = config('app.name');

        $replyTo = $smtp->custom_from_email ?? config('mail.from.address');

        $mailer = $this->buildMailer($settings);

        $mailer->send([], [], function (Message $message) use ($toEmail, $fromEmail, $fromName, $replyTo, $subject, $body) {
            $message->to($toEmail)
                ->from($fromEmail, $fromName)
                ->replyTo($replyTo)
                ->subject($subject)
                ->html($body);
        });
    }

    private function sendgridSettings(): array
    {
        // return [
        //     'host'       => config('mail.sendgrid.host'),
        //     'port'       => (int) config('mail.sendgrid.port'),
        //     'username'   => config('mail.sendgrid.username'),
        //     'password'   => config('mail.sendgrid.password'),
        //     'encryption' => config('mail.sendgrid.encryption'),
        // ];
        return [
            'host'       => env('SENDGRID_HOST', 'smtp.sendgrid.net'),
            'port'       => (int) env('SENDGRID_PORT', 587),
            'username'   => env('SENDGRID_USERNAME', 'apikey'),
            'password'   => env('SENDGRID_PASSWORD', ''),
            'encryption' => env('SENDGRID_ENCRYPTION', 'tls'),
        ];
    }

    private function customSmtpSettings(object $smtp): array
    {
        return [
            'host'       => $smtp->smtp_host,
            'port'       => (int) $smtp->port,
            'username'   => $smtp->username,
            'password'   => $smtp->password,
            'encryption' => $smtp->security_type,
        ];
    }

    /**
     * Build a one-off Laravel mailer from raw SMTP settings.
     */
    private function buildMailer(array $settings): \Illuminate\Mail\Mailer
    {
        $tls = strtolower($settings['encryption'] ?? '') === 'ssl';

        $transport = new EsmtpTransport(
            host: $settings['host'],
            port: $settings['port'],
            tls: $tls,
        );

        $transport->setUsername($settings['username']);
        $transport->setPassword($settings['password']);

        return new \Illuminate\Mail\Mailer(
            name: 'dynamic',
            views: app('view'),
            transport: $transport,
            events: app('events'),
        );
    }

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
            'channel'        => 'email',
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

        // ResponseAccess object — cast to array
        if ($shop instanceof \Gnikyt\BasicShopifyAPI\ResponseAccess) {
            $shop = $shop->toArray();
        }

        return $shop ?? [];
    }
}
