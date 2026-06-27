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
     * Configured POS payment methods as
     * [['key' => ..., 'label' => ..., 'credit' => bool], ...].
     * 'wallet' (store credit) is a reserved built-in and is never in this list.
     * A method flagged 'credit' records the tendered amount as a balance owing
     * (accounts receivable) rather than money received.
     *
     * @return array<int, array{key:string, label:string, credit:bool}>
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

            return ($key !== '' && $label !== '')
                ? ['key' => $key, 'label' => $label, 'credit' => ! empty($m['credit'])]
                : null;
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

    /** Keys of methods that leave a balance owing instead of paying the sale. */
    public function creditPaymentMethodKeys(): array
    {
        return array_values(array_map(
            fn ($m) => $m['key'],
            array_filter($this->paymentMethods(), fn ($m) => ! empty($m['credit'])),
        ));
    }

    /**
     * Built-in cash-drawer reasons (Shopify-style cash in / out) used when a
     * tenant hasn't configured its own. Each maps to the counterpart account the
     * journal posts to (the cash leg is always 1000).
     *
     * @var array<int, array{key:string, label:string, type:string, account:string}>
     */
    public const DEFAULT_CASH_REASONS = [
        ['key' => 'bank', 'label' => 'From bank / safe', 'type' => 'in', 'account' => '1010'],
        ['key' => 'owner', 'label' => 'Owner contribution', 'type' => 'in', 'account' => '3000'],
        ['key' => 'other', 'label' => 'Other', 'type' => 'in', 'account' => '3000'],
        ['key' => 'bank', 'label' => 'Bank deposit / safe drop', 'type' => 'out', 'account' => '1010'],
        ['key' => 'expense', 'label' => 'Expense / petty cash', 'type' => 'out', 'account' => '6000'],
        ['key' => 'owner', 'label' => 'Owner drawings', 'type' => 'out', 'account' => '3000'],
        ['key' => 'other', 'label' => 'Other', 'type' => 'out', 'account' => '6000'],
    ];

    /**
     * Configured cash-drawer reasons, falling back to the built-ins.
     *
     * @return array<int, array{key:string, label:string, type:string, account:string}>
     */
    public function cashReasons(): array
    {
        $rows = $this->setting('pos.cash_reasons');

        if (! is_array($rows) || $rows === []) {
            return self::DEFAULT_CASH_REASONS;
        }

        return array_values(array_filter(array_map(function ($r) {
            $key = trim((string) ($r['key'] ?? ''));
            $label = trim((string) ($r['label'] ?? ''));

            return ($key !== '' && $label !== '') ? [
                'key' => $key,
                'label' => $label,
                'type' => ($r['type'] ?? 'in') === 'out' ? 'out' : 'in',
                'account' => trim((string) ($r['account'] ?? '')),
            ] : null;
        }, $rows)));
    }

    /**
     * key => label map for one direction ('in'|'out') — the POS reason dropdown.
     *
     * @return array<string, string>
     */
    public function cashReasonsByType(string $type): array
    {
        $type = $type === 'out' ? 'out' : 'in';

        $out = [];
        foreach ($this->cashReasons() as $r) {
            if ($r['type'] === $type) {
                $out[$r['key']] = $r['label'];
            }
        }

        return $out ?: ['other' => 'Other'];
    }

    /** Counterpart account code configured for a (type, key) reason, or null. */
    public function cashReasonAccount(string $type, string $key): ?string
    {
        foreach ($this->cashReasons() as $r) {
            if ($r['type'] === $type && $r['key'] === $key && $r['account'] !== '') {
                return $r['account'];
            }
        }

        return null;
    }

    /** Built-in shipping methods used when a tenant hasn't configured its own. */
    public const DEFAULT_SHIPPING_METHODS = [
        ['label' => 'Standard Delivery', 'fee' => 5.00, 'pickup' => false],
        ['label' => 'Store Pickup', 'fee' => 0.00, 'pickup' => true],
    ];

    /**
     * Configured online shipping methods as
     * [['label' => ..., 'fee' => float, 'pickup' => bool], ...].
     * A 'pickup' method needs no delivery address.
     *
     * @return array<int, array{label:string, fee:float, pickup:bool}>
     */
    public function shippingMethods(): array
    {
        $methods = $this->setting('storefront.shipping_methods');

        if (! is_array($methods) || $methods === []) {
            return self::DEFAULT_SHIPPING_METHODS;
        }

        return array_values(array_filter(array_map(function ($m) {
            $label = trim((string) ($m['label'] ?? ''));

            return $label === '' ? null : [
                'label' => $label,
                'fee' => round((float) ($m['fee'] ?? 0), 2),
                'pickup' => ! empty($m['pickup']),
            ];
        }, $methods)));
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
