<?php

namespace App\Support;

use App\Models\Tenant;

/**
 * Holds the currently active tenant for the request lifecycle.
 *
 * Single-database multi-tenancy: every tenant-owned row carries a tenant_id,
 * and the BelongsToTenant trait transparently scopes all queries to the
 * current tenant resolved here.
 */
class Tenancy
{
    protected ?Tenant $tenant = null;

    public function set(?Tenant $tenant): void
    {
        $this->tenant = $tenant;
    }

    public function current(): ?Tenant
    {
        return $this->tenant;
    }

    public function id(): ?int
    {
        return $this->tenant?->id;
    }

    public function check(): bool
    {
        return $this->tenant !== null;
    }

    public function forget(): void
    {
        $this->tenant = null;
    }
}
