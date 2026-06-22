<?php

namespace App\Models\Storefront;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;

class Subscriber extends Model
{
    use BelongsToTenant;

    protected $fillable = ['tenant_id', 'email', 'name'];
}
