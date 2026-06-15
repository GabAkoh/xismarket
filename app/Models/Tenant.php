<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Tenant extends Model
{
    protected $fillable = [
        'name', 'slug', 'subdomain', 'email', 'phone',
        'currency', 'plan', 'trial_ends_at', 'is_active', 'settings',
    ];

    protected $casts = [
        'trial_ends_at' => 'datetime',
        'is_active' => 'boolean',
        'settings' => 'array',
    ];

    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    public function roles(): HasMany
    {
        return $this->hasMany(Role::class);
    }

    public function setting(string $key, $default = null)
    {
        return data_get($this->settings, $key, $default);
    }
}
