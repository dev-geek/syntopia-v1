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
                            {--limit= : Limit number of users to process}
                            {--email= : Process specific user by email}
                            {--tenant-only : Only fix missing tenant_id, skip password binding}
                            {--password-only : Only fix password binding, skip tenant creation}';

    protected $description = 'Fix old users missing tenant_id and/or password binding - ensures both are set up correctly';

    private PasswordBindingService $passwordBindingService;
    private int $successCount = 0;
    private int $tenantCreatedCount = 0;
    private int $passwordBoundCount = 0;
    private int $errorCount = 0;
    private int $skippedCount = 0;
    private array $errors = [];

    public function __construct(PasswordBindingService $passwordBindingService)
    {
        parent::__construct();
        $this->passwordBindingService = $passwordBindingService;
    }

    public function handle()
    {
        $dryRun = $this->option('dry-run');
        $limit = $this->option('limit') ? (int) $this->option('limit') : null;
        $email = $this->option('email');
        $tenantOnly = $this->option('tenant-only');
        $passwordOnly = $this->option('password-only');

        if ($dryRun) {
            $this->warn('DRY RUN MODE - No changes will be made');
        }

        // Find users that need fixing
        $query = User::query()
            ->whereDoesntHave('roles', function ($q) {
                $q->whereIn('name', ['Super Admin', 'Sub Admin']);
            })
            ->whereNotNull('subscriber_password'); // Only users with subscriber_password can be fixed

        if ($email) {
            $query->where('email', $email);
        } else {
            // Find users who need fixing:
            // - Users without tenant_id (need tenant + password binding)
            // - Users with tenant_id (need password binding check)
            if ($tenantOnly) {
                // Only find users without tenant_id
                $query->whereNull('tenant_id');
            } elseif ($passwordOnly) {
                // Only find users with tenant_id (they need password binding)
                $query->whereNotNull('tenant_id');
            } else {
                // Find all users (will check both tenant and password binding)
                // This includes users without tenant_id AND users with tenant_id
                $query->where(function ($q) {
                    $q->whereNull('tenant_id')
                      ->orWhereNotNull('tenant_id');
                });
            }
        }

        $users = $query->orderBy('id')->get();

        if ($limit) {
            $users = $users->take($limit);
        }

        $totalUsers = $users->count();

        if ($totalUsers === 0) {
            $this->info('No users found that need fixing.');
            return 0;
        }

        $this->info("Found {$totalUsers} user(s) to process.");

        if (!$this->confirm('Do you want to proceed?', true)) {
            $this->info('Cancelled.');
            return 0;
        }

        $bar = $this->output->createProgressBar($totalUsers);
        $bar->start();

        foreach ($users as $user) {
            try {
                $this->processUser($user, $dryRun, $tenantOnly, $passwordOnly);
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
                    'error' => $e->getMessage()
                ]);
            }
            $bar->advance();
        }

        $bar->finish();
        $this->newLine(2);

        // Show summary
        $this->displaySummary($dryRun);

        return 0;
    }

    private function processUser(User $user, bool $dryRun, bool $tenantOnly, bool $passwordOnly): void
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
        $this->info('=== Summary ===');
        $this->line("Users successfully processed: {$this->successCount}");
        $this->line("Tenants created: {$this->tenantCreatedCount}");
        $this->line("Passwords bound: {$this->passwordBoundCount}");
        $this->line("Errors: {$this->errorCount}");
        $this->line("Skipped: {$this->skippedCount}");

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

