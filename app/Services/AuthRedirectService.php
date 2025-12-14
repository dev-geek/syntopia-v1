<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\Log;

class AuthRedirectService
{
    public function getRedirectForUser(User $user, string $successMessage = 'Success!'): \Illuminate\Http\RedirectResponse
    {
        if ($user->hasAnyRole(['Super Admin', 'Sub Admin'])) {
            session()->forget('url.intended');
            session()->forget('verification_intended_url');
            return redirect()->route('admin.dashboard')->with('success', $successMessage);
        }

        if ($user->hasRole('User')) {
            $intendedUrl = $this->getIntendedUrl();
            if ($intendedUrl) {
                return redirect()->to($intendedUrl)->with('success', $successMessage);
            }

            if ($this->hasActiveSubscription($user)) {
                return redirect()->route('user.dashboard')->with('success', $successMessage);
            } else {
                return redirect()->route('subscription')->with('success', $successMessage);
            }
        }

        session()->forget('url.intended');
        session()->forget('verification_intended_url');
        return redirect()->route('user.profile')->with('success', $successMessage);
    }

    private function getIntendedUrl(): ?string
    {
        if (session()->has('verification_intended_url')) {
            $intendedUrl = session('verification_intended_url');
            if (!$this->isAdminRoute($intendedUrl)) {
                session()->forget('verification_intended_url');
                return $intendedUrl;
            }
            session()->forget('verification_intended_url');
        }

        if (session()->has('url.intended')) {
            $intendedUrl = session('url.intended');
            if (!$this->isAdminRoute($intendedUrl)) {
                session()->forget('url.intended');
                return $intendedUrl;
            }
            session()->forget('url.intended');
        }

        return null;
    }

    private function isAdminRoute(string $url): bool
    {
        return str_starts_with($url, '/admin') || str_contains($url, '/admin/');
    }

    private function hasActiveSubscription(User $user): bool
    {
        if (!$user->is_subscribed || !$user->package) {
            return false;
        }

        if (strtolower($user->package->name) === 'free') {
            return true;
        }

        $activeLicense = $user->userLicence;
        if (!$activeLicense || !$activeLicense->isActive()) {
            return false;
        }

        if ($activeLicense->isExpired()) {
            return false;
        }

        return true;
    }
}
