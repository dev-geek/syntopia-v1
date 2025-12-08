<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Services\TenantAssignmentService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class RetryTenantAssignment extends Command
{
    protected $signature = 'tenant:retry-assignment
                            {--limit= : Limit number of users to process per run}
                            {--dry-run : Run without making changes}';

    protected $description = 'Retry tenant_id assignment for users without tenant_id';

    private TenantAssignmentService $tenantAssignmentService;
    private int $successCount = 0;
    private int $errorCount = 0;
    private int $skippedCount = 0;

    public function __construct(TenantAssignmentService $tenantAssignmentService)
    {
        parent::__construct();
        $this->tenantAssignmentService = $tenantAssignmentService;
    }

    public function handle()
    {
        $limit = $this->option('limit') ? (int) $this->option('limit') : null;
        $dryRun = $this->option('dry-run');

        if ($dryRun) {
            $this->info('DRY RUN MODE - No changes will be made');
        }

        $this->info('Starting tenant_id assignment retry...');

        $query = User::whereNull('tenant_id')
            ->whereNotNull('subscriber_password')
            ->where('status', '>=', 0);

        if ($limit) {
            $query->limit($limit);
        }

        $users = $query->get();

        if ($users->isEmpty()) {
            $this->info('No users found without tenant_id.');
            return Command::SUCCESS;
        }

        $this->info("Found {$users->count()} user(s) without tenant_id.");

        $bar = $this->output->createProgressBar($users->count());
        $bar->start();

        foreach ($users as $user) {
            try {
                if ($dryRun) {
                    $this->line("\n[DRY RUN] Would retry tenant assignment for user: {$user->email} (ID: {$user->id})");
                    $this->skippedCount++;
                } else {
                    $result = $this->tenantAssignmentService->assignTenant($user);

                    if ($result['success']) {
                        $this->successCount++;
                        $this->line("\n✓ Successfully assigned tenant_id for user: {$user->email} (ID: {$user->id})");
                    } else {
                        $this->errorCount++;
                        $this->line("\n✗ Failed to assign tenant_id for user: {$user->email} (ID: {$user->id}) - {$result['error_message']}");
                    }
                }
            } catch (\Exception $e) {
                $this->errorCount++;
                $this->line("\n✗ Exception for user: {$user->email} (ID: {$user->id}) - {$e->getMessage()}");
                Log::error('Exception during tenant assignment retry command', [
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

        Log::info('Tenant assignment retry command completed', [
            'success_count' => $this->successCount,
            'error_count' => $this->errorCount,
            'skipped_count' => $this->skippedCount,
            'total_processed' => $users->count()
        ]);

        return Command::SUCCESS;
    }
}

