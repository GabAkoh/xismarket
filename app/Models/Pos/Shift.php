<?php

namespace App\Models\Pos;

use App\Models\Concerns\BelongsToTenant;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Shift extends Model
{
    use BelongsToTenant;

    protected $fillable = [
        'tenant_id', 'register_id', 'user_id', 'opened_at', 'closed_at',
        'opening_float', 'closing_amount', 'expected_amount', 'status', 'notes',
    ];

    protected function casts(): array
    {
        return [
            'opened_at' => 'datetime',
            'closed_at' => 'datetime',
            'opening_float' => 'decimal:2',
            'closing_amount' => 'decimal:2',
            'expected_amount' => 'decimal:2',
        ];
    }

    public function register(): BelongsTo
    {
        return $this->belongsTo(Register::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function sales(): HasMany
    {
        return $this->hasMany(Sale::class);
    }

    public function cashMovements(): HasMany
    {
        return $this->hasMany(CashMovement::class);
    }

    /** Total manual cash added to the drawer this shift. */
    public function cashIn(): float
    {
        return round((float) $this->cashMovements()->where('type', 'in')->sum('amount'), 2);
    }

    /** Total manual cash removed from the drawer this shift. */
    public function cashOut(): float
    {
        return round((float) $this->cashMovements()->where('type', 'out')->sum('amount'), 2);
    }

    /** Net drawer effect of manual movements (cash in − cash out). */
    public function netCashMovements(): float
    {
        return round($this->cashIn() - $this->cashOut(), 2);
    }

    public function isOpen(): bool
    {
        return $this->status === 'open';
    }
}
