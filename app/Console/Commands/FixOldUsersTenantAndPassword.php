<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Services\PasswordBindingService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class FixOldUsersTenantAndPassword extends Command
{
    protected $signature = 'users:fix-tenant-password
                            {--dry-run : Run without making changes}
                            {--limit= : Limit number of users to process per run}
                            {--batch-size=50 : Number of users to process in each batch}
                            {--email= : Process specific user by email}
                            {--tenant-only : Only fix missing tenant_id, skip password binding}
                            {--password-only : Only fix password binding, skip tenant creation}
                            {--force : Skip confirmation prompt (for cron jobs)}
                            {--no-progress : Disable progress bar (for cron jobs)}
                            {--delay=1 : Delay in seconds between API calls to avoid rate limiting}
                            {--skip-recent=24 : Skip users processed in the last N hours}';

    protected $description = 'Fix ALL users (old and new) missing tenant_id and/or password binding - Production ready for cron jobs';

    private PasswordBindingService $passwordBindingService;
    private int $successCount = 0;
    private int $tenantCreatedCount = 0;
    private int $passwordBoundCount = 0;
    private int $errorCount = 0;
    private int $skippedCount = 0;
    private int $alreadyProcessedCount = 0;
    private array $errors = [];
    private float $startTime;

    public function __construct(PasswordBindingService $passwordBindingService)
    {
        parent::__construct();
        $this->passwordBindingService = $passwordBindingService;
    }

    public function handle()
    {
        $this->startTime = microtime(true);

        $dryRun = $this->option('dry-run');
        $limit = $this->option('limit') ? (int) $this->option('limit') : null;
        $batchSize = (int) $this->option('batch-size');
        $email = $this->option('email');
        $tenantOnly = $this->option('tenant-only');
        $passwordOnly = $this->option('password-only');
        $force = $this->option('force');
        $noProgress = $this->option('no-progress');
        $delay = (float) $this->option('delay');
        $skipRecentHours = (int) $this->option('skip-recent');

        // Log command start
        Log::info('FixOldUsersTenantAndPassword command started', [
            'dry_run' => $dryRun,
            'limit' => $limit,
            'batch_size' => $batchSize,
            'email' => $email,
            'tenant_only' => $tenantOnly,
            'password_only' => $passwordOnly,
            'force' => $force,
            'skip_recent_hours' => $skipRecentHours
        ]);

        if ($dryRun) {
            $this->warn('DRY RUN MODE - No changes will be made');
            Log::info('Running in DRY RUN mode');
        }

        // Find ALL users (old and new) that need fixing
        // This includes:
        // - Users without tenant_id (need tenant + password binding)
        // - Users with tenant_id but missing password binding
        $query = User::query()
            ->whereDoesntHave('roles', function ($q) {
                $q->whereIn('name', ['Super Admin', 'Sub Admin']);
            })
            ->whereNotNull('subscriber_password'); // Only users with subscriber_password can be fixed

        // Skip recently processed users (check metadata for last processing time)
        if ($skipRecentHours > 0) {
            $cutoffTime = now()->subHours($skipRecentHours);
            // We'll check this in processUser to avoid complex query
        }

        if ($email) {
            // Process specific user by email
            $query->where('email', $email);
        } else {
            // Find ALL users who need fixing (old and new):
            if ($tenantOnly) {
                // Only find users without tenant_id
                $query->whereNull('tenant_id');
            } elseif ($passwordOnly) {
                // Only find users with tenant_id (they need password binding)
                $query->whereNotNull('tenant_id');
            } else {
                // Find ALL users (will check both tenant and password binding)
                // This includes:
                // - Users without tenant_id (need tenant creation + password binding)
                // - Users with tenant_id (need password binding verification)
                // No date filtering - handles both old and new users
                $query->where(function ($q) {
                    $q->whereNull('tenant_id')
                      ->orWhereNotNull('tenant_id');
                });
            }
        }

        // Order by ID for consistent processing
        $query->orderBy('id');

        // Get total count first
        $totalUsers = $query->count();

        if ($totalUsers === 0) {
            $this->info('No users found that need fixing.');
            Log::info('FixOldUsersTenantAndPassword: No users found that need fixing');
            return 0;
        }

        $this->info("Found {$totalUsers} user(s) to process.");

        // Skip confirmation if --force is used (default for cron)
        if (!$force && !$this->confirm('Do you want to proceed?', true)) {
            $this->info('Cancelled.');
            return 0;
        }

        // Apply limit if specified
        if ($limit) {
            $query->limit($limit);
        }

        $users = $query->get();
        $usersToProcess = $users->count();

        $this->info("Processing {$usersToProcess} user(s)...");

        // Create progress bar only if not in no-progress mode
        $bar = null;
        if (!$noProgress) {
            $bar = $this->output->createProgressBar($usersToProcess);
            $bar->start();
        }

        $processed = 0;
        foreach ($users as $user) {
            try {
                // Skip if recently processed
                if ($skipRecentHours > 0 && $this->wasRecentlyProcessed($user, $skipRecentHours)) {
                    $this->alreadyProcessedCount++;
                    if ($bar) $bar->advance();
                    continue;
                }

                $this->processUser($user, $dryRun, $tenantOnly, $passwordOnly, $delay);
                $processed++;

                // Add delay between API calls to avoid rate limiting
                if ($delay > 0 && $processed < $usersToProcess) {
                    usleep((int)($delay * 1000000)); // Convert seconds to microseconds
                }
            } catch (\Exception $e) {
                $this->errorCount++;
                $this->errors[] = [
                    'user_id' => $user->id,
                    'email' => $user->email,
                    'error' => $e->getMessage()
                ];
                Log::error('Error processing user in fix command', [
                    'user_id' => $user->id,
                    'email' => $user->email,
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString()
                ]);
            }

            if ($bar) {
                $bar->advance();
            }

            // Process in batches to avoid memory issues
            if ($processed > 0 && $processed % $batchSize === 0) {
                Log::info("Processed batch of {$batchSize} users", [
                    'total_processed' => $processed,
                    'success' => $this->successCount,
                    'errors' => $this->errorCount
                ]);
            }
        }

        if ($bar) {
            $bar->finish();
            $this->newLine(2);
        }

        // Show summary
        $this->displaySummary($dryRun);

        // Log final summary
        $executionTime = round(microtime(true) - $this->startTime, 2);
        Log::info('FixOldUsersTenantAndPassword command completed', [
            'execution_time_seconds' => $executionTime,
            'total_users' => $totalUsers,
            'users_processed' => $usersToProcess,
            'success' => $this->successCount,
            'tenants_created' => $this->tenantCreatedCount,
            'passwords_bound' => $this->passwordBoundCount,
            'errors' => $this->errorCount,
            'skipped' => $this->skippedCount,
            'already_processed' => $this->alreadyProcessedCount,
            'dry_run' => $dryRun
        ]);

        // Return appropriate exit code for cron monitoring
        if ($this->errorCount > 0) {
            return 1; // Exit with error code if there were errors
        }

        return 0;
    }

    /**
     * Check if user was recently processed by checking metadata
     */
    private function wasRecentlyProcessed(User $user, int $hours): bool
    {
        // Check if user has a recent metadata entry indicating processing
        // For now, we'll use a simple approach: check if user was updated recently
        // In a more sophisticated implementation, you could store processing metadata
        $cutoffTime = now()->subHours($hours);
        return $user->updated_at && $user->updated_at->isAfter($cutoffTime);
    }

    private function processUser(User $user, bool $dryRun, bool $tenantOnly, bool $passwordOnly, float $delay = 0): void
    {
        // Skip if user doesn't have subscriber_password
        if (!$user->subscriber_password) {
            $this->skippedCount++;
            Log::warning('User missing subscriber_password, skipping', [
                'user_id' => $user->id,
                'email' => $user->email
            ]);
            return;
        }

        $tenantFixed = false;
        $passwordFixed = false;

        // Step 1: Fix tenant_id if missing (unless password-only mode)
        if (!$passwordOnly && !$user->tenant_id) {
            if ($dryRun) {
                $this->info("  [DRY RUN] Would create tenant and bind password for: {$user->email}");
                $tenantFixed = true; // Mark as would-be-fixed for dry run
                $passwordFixed = true; // createTenantAndBindPassword also binds password
            } else {
                $tenantId = $this->createTenantAndBindPassword($user, $user->subscriber_password);

                if ($tenantId) {
                    $user->update(['tenant_id' => $tenantId]);
                    $user->refresh(); // Refresh to get updated tenant_id
                    $tenantFixed = true;
                    $this->tenantCreatedCount++;
                    // Note: createTenantAndBindPassword already binds password, so mark both as fixed
                    $passwordFixed = true;
                    $this->passwordBoundCount++;
                    Log::info('Successfully created tenant and bound password for user', [
                        'user_id' => $user->id,
                        'email' => $user->email,
                        'tenant_id' => $tenantId
                    ]);
                } else {
                    $this->errorCount++;
                    $this->errors[] = [
                        'user_id' => $user->id,
                        'email' => $user->email,
                        'error' => 'Failed to create tenant or bind password'
                    ];
                    return; // Can't proceed without tenant_id
                }
            }
        } elseif ($user->tenant_id) {
            $tenantFixed = true; // Already has tenant_id
        }

        // Step 2: Fix password binding for users who already have tenant_id (unless tenant-only mode)
        // Note: If we just created tenant above, password is already bound, so skip this step
        if (!$tenantOnly && !$passwordFixed && $user->tenant_id) {
            if ($dryRun) {
                if ($tenantFixed) {
                    $this->info("  [DRY RUN] Would bind password for: {$user->email}");
                }
                $passwordFixed = true; // Mark as would-be-fixed for dry run
            } else {
                // Always attempt password binding - it's safe to call even if already bound
                $bindResponse = $this->passwordBindingService->bindPassword($user, $user->subscriber_password);

                if ($bindResponse['success']) {
                    $passwordFixed = true;
                    $this->passwordBoundCount++;
                    Log::info('Successfully bound password for user', [
                        'user_id' => $user->id,
                        'email' => $user->email,
                        'tenant_id' => $user->tenant_id
                    ]);
                } else {
                    // Check if error indicates password is already bound
                    $errorMsg = $bindResponse['error_message'] ?? '';
                    if (str_contains(strtolower($errorMsg), 'already') ||
                        str_contains(strtolower($errorMsg), 'bound') ||
                        str_contains(strtolower($errorMsg), 'exist')) {
                        // Password likely already bound - that's okay
                        $passwordFixed = true;
                        $this->passwordBoundCount++; // Count as success since it's already bound
                        Log::info('Password appears to already be bound for user', [
                            'user_id' => $user->id,
                            'email' => $user->email
                        ]);
                    } else {
                        // Real error - log it
                        $this->errorCount++;
                        $this->errors[] = [
                            'user_id' => $user->id,
                            'email' => $user->email,
                            'error' => 'Password binding failed: ' . $errorMsg
                        ];
                        Log::warning('Password binding failed', [
                            'user_id' => $user->id,
                            'email' => $user->email,
                            'error' => $errorMsg
                        ]);
                    }
                }
            }
        } else {
            $passwordFixed = true; // Skipped in tenant-only mode
        }

        // Count as success if both are fixed (or would be fixed in dry run)
        if ($tenantFixed && $passwordFixed) {
            $this->successCount++;
        } elseif (!$dryRun) {
            // If not dry run and something failed, it's already counted in errorCount
            // But we might have partial success
            if ($tenantFixed || $passwordFixed) {
                // Partial success - don't double count
            }
        }
    }

    private function createTenantAndBindPassword(User $user, string $plainPassword): ?string
    {
        if (!$this->validatePasswordFormat($plainPassword)) {
            Log::error('Password format validation failed', [
                'user_id' => $user->id,
                'password_length' => strlen($plainPassword)
            ]);
            return null;
        }

        $baseUrl = rtrim(config('services.xiaoice.base_url', 'https://openapi.xiaoice.com/vh-cp'), '/');

        try {
            // Create the tenant
            $createResponse = Http::timeout(30)
                ->connectTimeout(15)
                ->retry(3, 1000)
                ->withHeaders([
                    'subscription-key' => config('services.xiaoice.subscription_key', '5c745ccd024140ffad8af2ed7a30ccad'),
                    'Content-Type' => 'application/json',
                    'Accept' => 'application/json'
                ])
                ->post($baseUrl . '/api/partner/tenant/create', [
                    'name' => $user->name,
                    'regionCode' => 'CN',
                    'adminName' => $user->name,
                    'adminEmail' => $user->email,
                    'adminPhone' => '',
                    'adminPassword' => $plainPassword,
                    'appIds' => [1],
                ]);

            $createJson = $createResponse->json();

            // Handle case where user is already registered in tenant system
            if (isset($createJson['code']) && $createJson['code'] == 730 && str_contains($createJson['message'], '管理员已注册其他企业')) {
                Log::warning('User already registered in tenant system, attempting to extract tenant_id', [
                    'user_id' => $user->id,
                    'email' => $user->email
                ]);
                // Try to get tenant_id from existing registration - this might not be possible
                // For now, we'll skip and just try password binding
                // The user might need manual intervention
                return null;
            }

            if (!$createResponse->successful()) {
                Log::error('Failed to create tenant', [
                    'user_id' => $user->id,
                    'status' => $createResponse->status(),
                    'response' => $createResponse->body()
                ]);
                return null;
            }

            $tenantId = $createJson['data']['tenantId'] ?? null;
            if (!$tenantId) {
                Log::error('Failed to extract tenantId from create response', [
                    'user_id' => $user->id,
                    'response' => $createJson
                ]);
                return null;
            }

            // Bind password
            $passwordBindResponse = Http::timeout(30)
                ->connectTimeout(15)
                ->retry(3, 1000)
                ->withHeaders([
                    'subscription-key' => config('services.xiaoice.subscription_key', '5c745ccd024140ffad8af2ed7a30ccad'),
                    'Content-Type' => 'application/json',
                    'Accept' => 'application/json'
                ])
                ->post($baseUrl . '/api/partner/tenant/user/password/bind', [
                    'email' => $user->email,
                    'phone' => '',
                    'newPassword' => $plainPassword
                ]);

            if (!$passwordBindResponse->successful()) {
                Log::error('Failed to bind password after tenant creation', [
                    'user_id' => $user->id,
                    'status' => $passwordBindResponse->status(),
                    'response' => $passwordBindResponse->body()
                ]);
                return null;
            }

            $bindJson = $passwordBindResponse->json();
            if (!isset($bindJson['code']) || $bindJson['code'] != 200) {
                Log::error('Password bind returned error code', [
                    'user_id' => $user->id,
                    'code' => $bindJson['code'] ?? null,
                    'message' => $bindJson['message'] ?? null,
                    'response' => $bindJson
                ]);
                return null;
            }

            return $tenantId;
        } catch (\Exception $e) {
            Log::error('Exception while creating tenant', [
                'user_id' => $user->id,
                'error' => $e->getMessage()
            ]);
            return null;
        }
    }

    private function validatePasswordFormat(string $password): bool
    {
        $pattern = '/^(?=.*[0-9])(?=.*[A-Z])(?=.*[a-z])(?=.*[,.<>{}~!@#$%^&_])[0-9A-Za-z,.<>{}~!@#$%^&_]{8,30}$/';
        return preg_match($pattern, $password) === 1;
    }

    private function displaySummary(bool $dryRun): void
    {
        $executionTime = round(microtime(true) - $this->startTime, 2);

        $this->info('=== Summary ===');
        $this->line("Execution time: {$executionTime} seconds");
        $this->line("Users successfully processed: {$this->successCount}");
        $this->line("Tenants created: {$this->tenantCreatedCount}");
        $this->line("Passwords bound: {$this->passwordBoundCount}");
        $this->line("Errors: {$this->errorCount}");
        $this->line("Skipped: {$this->skippedCount}");
        if ($this->alreadyProcessedCount > 0) {
            $this->line("Already processed (skipped): {$this->alreadyProcessedCount}");
        }

        if ($dryRun) {
            $this->warn('This was a DRY RUN - no changes were made');
        }

        if ($this->errorCount > 0 && count($this->errors) > 0) {
            $this->newLine();
            $this->error('Errors encountered:');
            $this->table(
                ['User ID', 'Email', 'Error'],
                array_map(fn($error) => [
                    $error['user_id'],
                    $error['email'],
                    $error['error']
                ], array_slice($this->errors, 0, 10)) // Show first 10 errors
            );

            if (count($this->errors) > 10) {
                $this->warn('... and ' . (count($this->errors) - 10) . ' more errors. Check logs for details.');
            }
        }
    }
}

