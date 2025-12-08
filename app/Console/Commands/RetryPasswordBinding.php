<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Services\PasswordBindingService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class RetryPasswordBinding extends Command
{
    protected $signature = 'password:retry-binding
                            {--limit= : Limit number of users to process per run}
                            {--dry-run : Run without making changes}';

    protected $description = 'Retry password binding for users with tenant_id but password binding may have failed';

    private PasswordBindingService $passwordBindingService;
    private int $successCount = 0;
    private int $errorCount = 0;
    private int $skippedCount = 0;

    public function __construct(PasswordBindingService $passwordBindingService)
    {
        parent::__construct();
        $this->passwordBindingService = $passwordBindingService;
    }

    public function handle()
    {
        $limit = $this->option('limit') ? (int) $this->option('limit') : null;
        $dryRun = $this->option('dry-run');

        if ($dryRun) {
            $this->info('DRY RUN MODE - No changes will be made');
        }

        $this->info('Starting password binding retry...');

        $query = User::whereNotNull('tenant_id')
            ->whereNotNull('subscriber_password')
            ->where('status', '>=', 0);

        if ($limit) {
            $query->limit($limit);
        }

        $users = $query->get();

        if ($users->isEmpty()) {
            $this->info('No users found with tenant_id and subscriber_password.');
            return Command::SUCCESS;
        }

        $this->info("Found {$users->count()} user(s) with tenant_id and subscriber_password.");

        $bar = $this->output->createProgressBar($users->count());
        $bar->start();

        foreach ($users as $user) {
            try {
                if ($dryRun) {
                    $this->line("\n[DRY RUN] Would retry password binding for user: {$user->email} (ID: {$user->id})");
                    $this->skippedCount++;
                } else {
                    $result = $this->passwordBindingService->bindPassword($user, $user->subscriber_password);

                    if ($result['success']) {
                        $this->successCount++;
                        $this->line("\n✓ Successfully bound password for user: {$user->email} (ID: {$user->id})");
                    } else {
                        $this->errorCount++;
                        $this->line("\n✗ Failed to bind password for user: {$user->email} (ID: {$user->id}) - {$result['error_message']}");
                    }
                }
            } catch (\Exception $e) {
                $this->errorCount++;
                $this->line("\n✗ Exception for user: {$user->email} (ID: {$user->id}) - {$e->getMessage()}");
                Log::error('Exception during password binding retry command', [
                    'user_id' => $user->id,
                    'email' => $user->email,
                    'exception' => $e->getMessage()
                ]);
            }

            $bar->advance();
        }

        $bar->finish();
        $this->newLine(2);

        $this->info("Summary:");
        $this->info("  Success: {$this->successCount}");
        $this->info("  Errors: {$this->errorCount}");
        if ($dryRun) {
            $this->info("  Skipped (dry run): {$this->skippedCount}");
        }

        Log::info('Password binding retry command completed', [
            'success_count' => $this->successCount,
            'error_count' => $this->errorCount,
            'skipped_count' => $this->skippedCount,
            'total_processed' => $users->count()
        ]);

        return Command::SUCCESS;
    }
}

