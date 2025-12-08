<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class CleanupDuplicatePackages extends Seeder
{
    /**
     * Clean up duplicate packages, keeping only the most recent one for each package name.
     */
    public function run(): void
    {
        // Get all package names that have duplicates
        $duplicates = DB::table('packages')
            ->select('name', DB::raw('COUNT(*) as count'))
            ->groupBy('name')
            ->having('count', '>', 1)
            ->get();

        foreach ($duplicates as $duplicate) {
            // Get all IDs for this package name, ordered by ID descending (newest first)
            $packageIds = DB::table('packages')
                ->where('name', $duplicate->name)
                ->orderBy('id', 'desc')
                ->pluck('id')
                ->toArray();

            // Keep the first (newest) one, delete the rest
            if (count($packageIds) > 1) {
                $keepId = array_shift($packageIds); // Keep the newest
                DB::table('packages')
                    ->where('name', $duplicate->name)
                    ->whereIn('id', $packageIds)
                    ->delete();

                $this->command->info("Cleaned up duplicates for '{$duplicate->name}'. Kept ID: {$keepId}, Deleted: " . count($packageIds) . " duplicates.");
            }
        }

        $this->command->info('Duplicate packages cleanup completed!');
    }
}

