<?php

namespace App\Console\Commands;

use App\Models\FreePlanAttempt;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class ManageFreePlanAttempts extends Command
{
    protected $signature = 'free-plan:manage 
                            {action : Action to perform (list, unblock, stats)}
                            {--ip= : IP address to filter by}
                            {--email= : Email to filter by}
                            {--days=30 : Number of days to look back}';

    protected $description = 'Manage free plan attempts and blocked registrations';

    public function handle()
    {
        $action = $this->argument('action');
        $ip = $this->option('ip');
        $email = $this->option('email');
        $days = (int) $this->option('days');

        match ($action) {
            'list' => $this->listAttempts($ip, $email, $days),
            'unblock' => $this->unblockAttempts($ip, $email),
            'stats' => $this->showStats($days),
            default => $this->error('Invalid action. Use: list, unblock, or stats'),
        };
    }

    private function listAttempts(?string $ip, ?string $email, int $days): void
    {
        $query = FreePlanAttempt::recent($days);

        if ($ip) {
            $query->byIp($ip);
        }

        if ($email) {
            $query->byEmail($email);
        }

        $attempts = $query->orderBy('created_at', 'desc')->get();

        if ($attempts->isEmpty()) {
            $this->info('No attempts found.');
            return;
        }

        $this->table(
            ['ID', 'IP', 'Email', 'Blocked', 'Created At'],
            $attempts->map(fn($attempt) => [
                $attempt->id,
                $attempt->ip_address,
                $attempt->email ?? 'N/A',
                $attempt->is_blocked ? 'Yes' : 'No',
                $attempt->created_at->format('Y-m-d H:i:s'),
            ])
        );
    }

    private function unblockAttempts(?string $ip, ?string $email): void
    {
        $query = FreePlanAttempt::blocked();

        if ($ip) {
            $query->byIp($ip);
        }

        if ($email) {
            $query->byEmail($email);
        }

        $count = $query->count();

        if ($count === 0) {
            $this->info('No blocked attempts found to unblock.');
            return;
        }

        if ($this->confirm("Unblock {$count} attempts?")) {
            $query->update([
                'is_blocked' => false,
                'blocked_at' => null,
                'block_reason' => null,
            ]);

            $this->info("Successfully unblocked {$count} attempts.");
        }
    }

    private function showStats(int $days): void
    {
        $stats = [
            'Total Attempts' => FreePlanAttempt::recent($days)->count(),
            'Blocked Attempts' => FreePlanAttempt::recent($days)->blocked()->count(),
            'Unique IPs' => FreePlanAttempt::recent($days)->distinct('ip_address')->count(),
            'Unique Emails' => FreePlanAttempt::recent($days)->whereNotNull('email')->distinct('email')->count(),
        ];

        $this->info("Statistics for the last {$days} days:");
        foreach ($stats as $label => $value) {
            $this->line("  {$label}: {$value}");
        }
    }
} 