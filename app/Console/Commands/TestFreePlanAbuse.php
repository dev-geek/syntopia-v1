<?php

namespace App\Console\Commands;

use App\Models\FreePlanAttempt;
use App\Services\DeviceFingerprintService;
use Illuminate\Console\Command;
use Illuminate\Http\Request;

class TestFreePlanAbuse extends Command
{
    protected $signature = 'free-plan:test 
                            {action : Action to perform (create, check, clear)}
                            {--ip=127.0.0.1 : IP address to test}
                            {--email=test@example.com : Email to test}
                            {--count=1 : Number of attempts to create}';

    protected $description = 'Test free plan abuse prevention system';

    public function handle()
    {
        $action = $this->argument('action');
        $ip = $this->option('ip');
        $email = $this->option('email');
        $count = (int) $this->option('count');

        match ($action) {
            'create' => $this->createAttempts($ip, $email, $count),
            'check' => $this->checkAttempts($ip, $email),
            'clear' => $this->clearAttempts(),
            default => $this->error('Invalid action. Use: create, check, or clear'),
        };
    }

    private function createAttempts(string $ip, string $email, int $count): void
    {
        $this->info("Creating {$count} test attempts for IP: {$ip}, Email: {$email}");

        for ($i = 0; $i < $count; $i++) {
            $request = new Request();
            $request->merge(['email' => $email]);
            
            // Mock the request IP and user agent
            $request->server->set('REMOTE_ADDR', $ip);
            $request->headers->set('User-Agent', 'Test Browser/1.0');
            $request->headers->set('Accept-Language', 'en-US,en;q=0.9');
            $request->headers->set('Accept-Encoding', 'gzip, deflate');
            $request->headers->set('Accept', 'text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8');
            $request->headers->set('Connection', 'keep-alive');
            $request->headers->set('Upgrade-Insecure-Requests', '1');
            $request->headers->set('Sec-Fetch-Dest', 'document');
            $request->headers->set('Sec-Fetch-Mode', 'navigate');
            $request->headers->set('Sec-Fetch-Site', 'none');
            $request->headers->set('Sec-Fetch-User', '?1');

            $deviceFingerprintService = new DeviceFingerprintService();
            $deviceFingerprintService->recordAttempt($request);

            $this->line("Created attempt #" . ($i + 1));
        }

        $this->info("Successfully created {$count} test attempts.");
    }

    private function checkAttempts(string $ip, string $email): void
    {
        $this->info("Checking attempts for IP: {$ip}, Email: {$email}");

        $request = new Request();
        $request->merge(['email' => $email]);
        $request->server->set('REMOTE_ADDR', $ip);
        $request->headers->set('User-Agent', 'Test Browser/1.0');

        $deviceFingerprintService = new DeviceFingerprintService();

        $isBlocked = $deviceFingerprintService->isBlocked($request);
        $hasRecentAttempts = $deviceFingerprintService->hasRecentAttempts($request);
        $shouldBlock = $deviceFingerprintService->shouldBlock($request);

        $this->table(
            ['Check', 'Result'],
            [
                ['Is Blocked', $isBlocked ? 'Yes' : 'No'],
                ['Has Recent Attempts', $hasRecentAttempts ? 'Yes' : 'No'],
                ['Should Block', $shouldBlock ? 'Yes' : 'No'],
            ]
        );

        // Show recent attempts
        $attempts = FreePlanAttempt::byIp($ip)
            ->orWhere('email', $email)
            ->orderBy('created_at', 'desc')
            ->limit(5)
            ->get();

        if ($attempts->isNotEmpty()) {
            $this->info("\nRecent attempts:");
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
    }

    private function clearAttempts(): void
    {
        $count = FreePlanAttempt::count();
        
        if ($this->confirm("Delete all {$count} attempts?")) {
            FreePlanAttempt::truncate();
            $this->info("Successfully cleared all attempts.");
        }
    }
} 