<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\Package;

class PackageSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $packages = [
            [
                'name' => 'Free',
                'price' => 0,
                'duration' => 'month',
                'features' => [
                    '1 user',
                    '1 livestream room',
                    '1 live broadcast (single anchor)',
                    'Lite Live Stream (one anchor)',
                    '1 livestream room',
                    '1 Q&A base',
                    '19 min live stream duration',
                    '5MB storage',
                ],
            ],
            [
                'name' => 'Starter',
                'price' => 380,
                'duration' => '60hrs a month',
                'features' => [
                    '1 user',
                    '1 livestream room',
                    '1 live broadcast (single anchor)',
                    'Lite Live Stream (one anchor)',
                    '1 livestream accounts',
                    '1 Q&A base',
                    '60 hrs streaming',
                    '1GB storage',
                    'AI: 10 creations, 10 rewrites',
                ],
            ],
            [
                'name' => 'Pro',
                'price' => 780,
                'duration' => '120hrs a month',
                'features' => [
                    '2 users',
                    '3 livestream room',
                    '3 live broadcast (single anchor)',
                    'Lite Live Stream (one anchor)',
                    'Pro Live Stream',
                    '3 livestream accounts',
                    '3 Q&A base',
                    '120 hrs streaming',
                    '1GB storage',
                    'AI: 30 creations, 30 rewrites',
                ],
            ],
            [
                'name' => 'Business',
                'price' => 2580,
                'duration' => 'unlimited',
                'features' => [
                    '3 user',
                    '1 livestream room',
                    '1 live broadcast',
                    'Lite Live Stream (one anchor)',
                    'Pro Live Stream',
                    'Video Live Stream',
                    '3 livestream accounts',
                    '3 Q&A base',
                    'Unlimited streaming',
                    '1GB storage',
                    'AI: 90 creations, 90 rewrites',
                ],
            ],
            [
                'name' => 'Enterprise',
                'price' => null,
                'duration' => 'custom',
                'features' => [
                    'Custom users & rooms',
                    'Custom livestream features',
                    'Custom Q&A bases',
                    'Custom AI & video tools',
                    'Unlimited resources',
                    'Tailored support & solutions',
                    'Dual Live Stream (two anchor in one live room)',
                ],
            ],
            // Add-on products (one-time purchases)
            [
                'name' => 'Avatar Customization (Clone Yourself)',
                'price' => 1380,
                'duration' => 'one-time',
                'features' => [
                    '7 min of training video recorded required from you',
                    'You get 1 Digital avatar',
                    'Step-by-step video recording guide will be provided - https://syntopia.ai/custom-avatar-shooting-guide/',
                    'Minor imperfections may remain',
                    'One-time setup, no annual fee',
                ],
            ],
        ];

        foreach ($packages as $packageData) {
            Package::updateOrCreate(
                ['name' => $packageData['name']],
                [
                    'price' => $packageData['price'],
                    'duration' => $packageData['duration'],
                    'features' => $packageData['features'],
                ]
            );
        }
    }
}
