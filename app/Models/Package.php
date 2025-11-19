<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Package extends Model
{
    use HasFactory;
    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'name',
        'price',
        'duration',
        'features',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array
     */
    protected $casts = [
        'features' => 'array',
    ];

    /**
     * Get the name of the package in lowercase
     *
     * @return string
     */
    public function getPackageNameLower()
    {
        return strtolower($this->name);
    }

    /**
     * Check if this is a free package
     * Free plans bypass payment gateway checkout (FastSpring, PayProGlobal, Paddle)
     * and are immediately assigned to the user
     *
     * @return bool
     */
    public function isFree()
    {
        return $this->price == 0 || strtolower($this->name) === 'free';
    }

    /**
     * Get the duration in days based on the duration string
     *
     * @return int|null
     */
    public function getDurationInDays()
    {
        if (!$this->duration) {
            return null;
        }

        // Handle different duration formats
        $duration = strtolower($this->duration);

        // Handle monthly durations
        if (strpos($duration, 'month') !== false) {
            // For monthly packages, return null since we'll handle this with Carbon's addMonth
            return null;
        }

        // Handle yearly durations
        if (strpos($duration, 'year') !== false || strpos($duration, 'yr') !== false) {
            $years = (int)str_replace(['year', 'years', 'yr', 'yrs'], '', $duration);
            return $years * 365; // 365 days per year
        }

        // Handle hourly durations
        if (strpos($duration, 'hr') !== false || strpos($duration, 'hour') !== false) {
            // For hourly packages, we'll assume 24 hours = 1 day
            $hours = (int)str_replace(['hr', 'hrs', 'hour', 'hours'], '', $duration);
            return ceil($hours / 24);
        }

        // Do not treat numeric durations as years to avoid misinterpretation
        if (is_numeric($duration)) {
            return null; // Prevent treating numeric values as years
        }

        return null;
    }

    /**
     * Get the number of months for monthly packages
     *
     * @return int|null
     */
    public function getMonthlyDuration()
    {
        if (!$this->duration) {
            return null;
        }

        $duration = strtolower($this->duration);

        // Handle explicit month durations (e.g., "1 month", "months")
        if (strpos($duration, 'month') !== false) {
            // Check for formats like "120hrs/month" or "X/month"
            if (preg_match('/(\d+hrs\/month|\d+\/month|month)/', $duration)) {
                return 1; // Treat as 1-month duration regardless of prefix
            }
            $months = (int)str_replace(['month', 'months'], '', $duration);
            return $months ?: 1; // If no number specified, assume 1 month
        }

        // Handle numeric durations (e.g., "1") as months for monthly packages
        if (is_numeric($duration) && strtolower($this->name) === 'monthly') {
            return (int)$duration ?: 1; // Treat numeric duration as months for 'Monthly' package
        }

        return null;
    }

    /**
     * Check if this is an enterprise package
     *
     * @return bool
     */
    public function isEnterprise()
    {
        return strtolower($this->name) === 'enterprise';
    }

    public function setFeaturesAttribute($value)
    {
        $this->attributes['features'] = is_array($value)
            ? json_encode($value)
            : $value;
    }
}
