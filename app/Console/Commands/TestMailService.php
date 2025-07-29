<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\MailService;
use App\Mail\VerifyEmail;
use App\Models\User;

class TestMailService extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'mail:test {email}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Test the mail service with a given email address';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $email = $this->argument('email');

        $this->info("Testing mail service for: {$email}");

        // Check if mail service is available
        if (!MailService::isAvailable()) {
            $this->error('Mail service is not available. Check your configuration.');
            return 1;
        }

        // Create a test user
        $user = new User([
            'email' => $email,
            'name' => 'Test User',
            'verification_code' => '123456'
        ]);

        // Test sending email
        $result = MailService::send($email, new VerifyEmail($user));

        if ($result['success']) {
            $this->info('✅ Mail sent successfully!');
            $this->info("Message: {$result['message']}");
        } else {
            $this->error('❌ Mail sending failed!');
            $this->error("Error: {$result['message']}");
            $this->error("Technical details: {$result['error']}");
            return 1;
        }

        return 0;
    }
}
