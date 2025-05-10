<?php

namespace App\Listeners;

use App\Models\UserLog;
use Illuminate\Auth\Events\Login;
use Illuminate\Auth\Events\Logout;
use Illuminate\Support\Facades\Request;

class LogUserActivity
{
    public function handle($event)
{
    $user = $event->user;

    if ($user) {
        $activity = $event instanceof Login 
            ? "{$user->name} Logged In" 
            : "{$user->name} Logged Out";

        UserLog::create([
            'user_id' => $user->id,
            'activity' => $activity,
            'ip_address' => Request::ip(),
            'user_agent' => Request::header('User-Agent'),
        ]);
    }
}

}

