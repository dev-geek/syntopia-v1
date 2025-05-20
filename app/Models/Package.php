<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Package extends Model
{
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
     *
     * @return bool
     */
    public function isFree()
    {
        return $this->price == 0;
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
