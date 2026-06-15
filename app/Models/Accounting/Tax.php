<?php

namespace App\Models\Accounting;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;

class Tax extends Model
{
    use BelongsToTenant;

    protected $fillable = [
        'tenant_id', 'name', 'rate', 'is_active',
    ];

    protected function casts(): array
    {
        return [
            'rate' => 'decimal:4',
            'is_active' => 'boolean',
        ];
    }
}
