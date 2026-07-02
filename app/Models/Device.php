<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Device extends Model
{
    use BelongsToTenant;

    protected $fillable = [
        'tenant_id', 'token', 'label', 'user_agent', 'approved', 'approved_by', 'last_seen_at',
    ];

    protected function casts(): array
    {
        return [
            'approved' => 'boolean',
            'last_seen_at' => 'datetime',
        ];
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }
}
