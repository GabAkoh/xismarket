<?php

namespace App\Models\Pos;

use App\Models\Concerns\BelongsToTenant;
use App\Models\Inventory\Warehouse;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Register extends Model
{
    use BelongsToTenant;

    protected $fillable = [
        'tenant_id', 'warehouse_id', 'name', 'code', 'is_active',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }

    public function shifts(): HasMany
    {
        return $this->hasMany(Shift::class);
    }

    public function sales(): HasMany
    {
        return $this->hasMany(Sale::class);
    }

    /** The currently open shift for this register, if any. */
    public function openShift(): ?Shift
    {
        return $this->shifts()->where('status', 'open')->latest('opened_at')->first();
    }
}
