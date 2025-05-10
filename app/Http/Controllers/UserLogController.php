<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\UserLog;

class UserLogController extends Controller
{
    public function index()
    {
        $logs = UserLog::with('user')->latest()->get();
        $userLogs = UserLog::latest()->get();

        return view('admin.logs', compact('logs','userLogs'));
    }

    public function dashboard()
    {
        $userLogs = UserLog::latest()->get(); // Get latest 10 logs
        return view('admin.includes.header', compact('userLogs'));
    }
}
