<?php

namespace App\Jobs;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class NotifyUserOfFailedPayment implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        public User $user,
        public array $webhookPayload
    ) {}

    public function handle(): void
    {
        try {
            Log::channel('billing')->info('Notifying user of failed payment', [
                'event' => 'payment_failed_notification',
                'customer' => $this->user->id,
                'user_id' => $this->user->id,
                'email' => $this->user->email
            ]);

            // Send email notification
            $this->sendEmailNotification();

            // Log the failed payment attempt
            $this->logFailedPayment();

            Log::channel('billing')->info('User notified of failed payment successfully', [
                'event' => 'payment_failed_notification',
                'customer' => $this->user->id
            ]);

        } catch (\Exception $e) {
            Log::channel('billing')->error('Failed to notify user of payment failure', [
                'event' => 'payment_failed_notification',
                'customer' => $this->user->id,
                'user_id' => $this->user->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            throw $e;
        }
    }

    protected function sendEmailNotification(): void
    {
        try {
            $subject = 'Payment Failed - Action Required';
            $message = $this->buildEmailMessage();

            Mail::raw($message, function ($mail) use ($subject) {
                $mail->to($this->user->email)
                    ->subject($subject);
            });

            Log::channel('billing')->info('Payment failure email sent', [
                'event' => 'payment_failed_email_sent',
                'customer' => $this->user->id,
                'user_id' => $this->user->id,
                'email' => $this->user->email
            ]);
        } catch (\Exception $e) {
            Log::channel('billing')->error('Failed to send payment failure email', [
                'event' => 'payment_failed_email_error',
                'customer' => $this->user->id,
                'user_id' => $this->user->id,
                'error' => $e->getMessage()
            ]);
        }
    }

    protected function buildEmailMessage(): string
    {
        $amount = $this->webhookPayload['amount'] ?? 'N/A';
        $currency = $this->webhookPayload['currency'] ?? 'USD';
        $subscriptionId = $this->webhookPayload['subscription_id'] ?? 'N/A';
        $failureDate = $this->webhookPayload['event_time'] ?? now()->toDateTimeString();

        return <<<EMAIL
Dear {$this->user->name},

We were unable to process your recent payment for your subscription.

Payment Details:
- Amount: {$currency} {$amount}
- Subscription ID: {$subscriptionId}
- Failure Date: {$failureDate}

Your subscription has been temporarily suspended. To restore your access, please update your payment method or contact our support team.

You can update your payment method by logging into your account at: {url('/user/subscription')}

If you have any questions, please don't hesitate to contact our support team.

Best regards,
Syntopia Team
EMAIL;
    }

    protected function logFailedPayment(): void
    {
        \App\Models\UserLog::create([
            'user_id' => $this->user->id,
            'action' => 'payment_failed',
            'description' => 'Payment failed for subscription',
            'metadata' => [
                'webhook_payload' => $this->webhookPayload,
                'failed_at' => now()->toDateTimeString()
            ]
        ]);
    }
}
