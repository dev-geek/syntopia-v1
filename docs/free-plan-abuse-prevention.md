# Free Plan Abuse Prevention System

This document outlines the implementation and configuration of the free plan abuse prevention system in Syntopia.

## Overview

The system prevents users from registering for multiple free trials from the same device or network by implementing device fingerprinting and tracking. It uses a combination of browser fingerprinting, IP tracking, and other techniques to identify and block potential abuse.

## How It Works

1. **Client-Side Fingerprinting**:
   - When a user visits the registration page, a unique fingerprint is generated based on their browser and device characteristics.
   - The fingerprint is stored in localStorage and sent with the registration request.
   - Additional device/browser information is collected and sent to the server for analysis.

2. **Server-Side Validation**:
   - The server validates registration attempts against the fingerprint and other identifiers.
   - Multiple registration attempts from the same device or network are blocked based on configurable thresholds.
   - Suspicious activity is logged for review.

3. **Blocking Mechanism**:
   - Users who exceed the allowed number of registration attempts are blocked.
   - Blocked users receive a clear error message explaining the situation.

## Configuration

The system can be configured by modifying the `config/free_plan_abuse.php` file. Here are the available options:

```php
return [
    /*
    |--------------------------------------------------------------------------
    | Maximum Allowed Registration Attempts
    |--------------------------------------------------------------------------
    |
    | This value determines the maximum number of registration attempts allowed
    | from the same device or IP address within the tracking period.
    |
    */
    'max_attempts' => env('FREE_PLAN_MAX_ATTEMPTS', 3),

    /*
    |--------------------------------------------------------------------------
    | Tracking Period (in days)
    |--------------------------------------------------------------------------
    |
    | The number of days to track registration attempts for each device/IP.
    |
    */
    'tracking_period_days' => env('FREE_PLAN_TRACKING_DAYS', 30),

    /*
    |--------------------------------------------------------------------------
    | Block Messages
    |--------------------------------------------------------------------------
    |
    | Customize the error messages shown to blocked users.
    |
    */
    'messages' => [
        'device_blocked' => 'Registration is not allowed from this device. Please contact support if you believe this is an error.',
        'too_many_attempts' => 'Too many registration attempts. Please try again later or contact support.',
    ],
];
```

## Database Schema

The system uses the `free_plan_attempts` table to track registration attempts. Here's the schema:

```php
Schema::create('free_plan_attempts', function (Blueprint $table) {
    $table->id();
    $table->string('ip_address', 45)->nullable();
    $table->text('user_agent')->nullable();
    $table->string('device_fingerprint')->nullable();
    $table->string('fingerprint_id', 64)->nullable();
    $table->string('email')->nullable();
    $table->boolean('is_blocked')->default(false);
    $table->timestamp('blocked_at')->nullable();
    $table->text('block_reason')->nullable();
    $table->json('data')->nullable();
    $table->timestamps();
    
    $table->index('ip_address');
    $table->index('email');
    $table->index('fingerprint_id');
});
```

## API Endpoints

- `POST /api/fingerprint` - Records device fingerprint data (protected by CSRF)

## JavaScript Integration

The client-side fingerprinting script is located at `public/js/fingerprint.js`. It automatically:
- Generates a unique fingerprint for each device
- Collects device/browser information
- Sends the data to the server
- Handles the registration form submission

## Testing

To test the system, you can use the provided test suite:

```bash
php artisan test tests/Feature/FreePlanAbuseTest.php
```

## Troubleshooting

### Common Issues

1. **False Positives**
   - If legitimate users are being blocked, check the `free_plan_attempts` table for their fingerprint.
   - Consider increasing the `max_attempts` value in the config.

2. **Fingerprint Generation Failures**
   - Ensure JavaScript is enabled in the browser.
   - Check the browser console for any errors.
   - Verify that the `fingerprint.js` file is being loaded correctly.

3. **Performance Issues**
   - The fingerprinting process is designed to be lightweight, but if you experience performance issues, consider:
     - Reducing the amount of data collected
     - Implementing rate limiting
     - Using a CDN for the fingerprint.js file

## Security Considerations

- The system stores minimal personally identifiable information (PII).
- IP addresses are stored for security purposes but can be anonymized if needed.
- Consider implementing additional security measures like CAPTCHA for suspicious registration attempts.

## Future Improvements

- Implement a manual review process for blocked users
- Add more sophisticated bot detection
- Provide admin interface for managing blocked devices
- Implement IP reputation scoring
