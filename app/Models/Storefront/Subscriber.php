<?php

namespace App\Models\Storefront;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class Subscriber extends Model
{
    use BelongsToTenant;

    protected $fillable = ['tenant_id', 'email', 'name', 'token', 'unsubscribed_at'];

    protected function casts(): array
    {
        return [
            'unsubscribed_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        // Every subscriber gets a stable token for one-click unsubscribe links.
        static::creating(function (Subscriber $subscriber) {
            if (empty($subscriber->token)) {
                $subscriber->token = Str::random(40);
            }
        });
    }

    /** Subscribers still receiving emails (not opted out). */
    public function scopeActive($query)
    {
        return $query->whereNull('unsubscribed_at');
    }

    public function isSubscribed(): bool
    {
        return $this->unsubscribed_at === null;
    }
}
