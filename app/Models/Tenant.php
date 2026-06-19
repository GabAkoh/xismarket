<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Tenant extends Model
{
    protected $fillable = [
        'name', 'slug', 'subdomain', 'email', 'phone', 'address',
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

    /** Built-in methods used when a tenant hasn't configured its own. */
    public const DEFAULT_PAYMENT_METHODS = [
        ['key' => 'cash', 'label' => 'Cash'],
        ['key' => 'card', 'label' => 'Card'],
        ['key' => 'other', 'label' => 'Other'],
    ];

    /**
     * Configured POS payment methods as [['key' => ..., 'label' => ...], ...].
     * 'wallet' (store credit) is a reserved built-in and is never in this list.
     *
     * @return array<int, array{key:string, label:string}>
     */
    public function paymentMethods(): array
    {
        $methods = $this->setting('pos.payment_methods');

        if (! is_array($methods) || $methods === []) {
            return self::DEFAULT_PAYMENT_METHODS;
        }

        return array_values(array_filter(array_map(function ($m) {
            $key = trim((string) ($m['key'] ?? ''));
            $label = trim((string) ($m['label'] ?? ''));

            return ($key !== '' && $label !== '') ? ['key' => $key, 'label' => $label] : null;
        }, $methods)));
    }

    /** Method keys only (e.g. ['cash', 'transfer']). */
    public function paymentMethodKeys(): array
    {
        return array_column($this->paymentMethods(), 'key');
    }

    /** key => label map for display (e.g. ['cash' => 'Cash']). */
    public function paymentMethodLabels(): array
    {
        return array_column($this->paymentMethods(), 'label', 'key');
    }

    /** Display symbol for the tenant's currency, falling back to the ISO code. */
    public function currencySymbol(): string
    {
        return match ($this->currency) {
            'NGN' => 'N',
            'USD' => '$',
            'EUR' => '€',
            'GBP' => '£',
            'GHS' => '₵',
            'ZAR' => 'R',
            'INR' => '₹',
            'JPY' => '¥',
            default => (string) ($this->currency ?? ''),
        };
    }
}
