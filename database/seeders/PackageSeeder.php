<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class PackageSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        DB::table('packages')->insert([
            [
                'name' => 'Free',
                'price' => 0,
                'duration' => 'monthly',
                'features' => json_encode([
                    '1 user',
                    '1 livestream room',
                    '1 live broadcast (single anchor)',
                    'Lite Live Stream (one anchor)',
                    '1 Q&A base',
                    '120 min live stream duration',
                    '5MB storage',
                    '5 min video synthesis',
                ]),
            ],
            [
                'name' => 'Starter',
                'price' => 390,
                'duration' => '60hrs/month',
                'features' => json_encode([
                    '1 user',
                    '1 livestream room',
                    '1 live broadcast (single anchor)',
                    'Lite Live Stream (one anchor)',
                    '1 livestream account',
                    '1 Q&A base',
                    '60 hrs streaming',
                    '5MB storage',
                    'AI: 10 creations, 10 rewrites',
                    '5 min video synthesis',
                ]),
            ],
            [
                'name' => 'Pro',
                'price' => 780,
                'duration' => '120hrs/month',
                'features' => json_encode([
                    '2 users',
                    '3 livestream rooms',
                    '3 live broadcasts (single anchor)',
                    'Dual Live Stream (two anchor in one live room)',
                    'Pro Live Stream',
                    '3 livestream accounts',
                    '3 Q&A base',
                    '120 hrs streaming',
                    '5MB storage',
                    'AI: 30 creations, 30 rewrites',
                    '20 min video synthesis',
                ]),
            ],
            [
                'name' => 'Business',
                'price' => 2800,
                'duration' => 'monthly',
                'features' => json_encode([
                    '3 users',
                    '1 livestream room',
                    '1 live broadcast',
                    'Dual Live Stream (two anchor in one live room)',
                    'Pro Live Stream',
                    'Video Live Stream',
                    '3 livestream accounts',
                    '3 Q&A base',
                    'Unlimited streaming',
                    '5MB storage',
                    'AI: 90 creations, 90 rewrites',
                    '60 min video synthesis',
                ]),
            ],
            [
                'name' => 'Enterprise',
                'price' => null,
                'duration' => 'custom',
                'features' => json_encode([
                    'Custom users & rooms',
                    'Custom livestream features',
                    'Custom Q&A bases',
                    'Custom AI & video tools',
                    'Unlimited resources',
                    'Tailored support & solutions',
                ]),
            ],
            // Add-on products (one-time purchases)
            [
                'name' => 'Avatar Customization',
                'price' => 2800,
                'duration' => 'one-time',
                'features' => json_encode([
                    'Create a hyperrealistic AI avatar matching your face, hairstyle, and outfit',
                    'Includes recording guidelines',
                    'One-time setup; no recurring fees',
                ]),
            ],
            [
                'name' => 'Voice Customization',
                'price' => 2200,
                'duration' => 'one-time',
                'features' => json_encode([
                    'Custom AI voice based on your audio sample',
                    'Guide provided for best results',
                    'One-time setup; no usage fees',
                ]),
            ],
        ]);
    }
}
