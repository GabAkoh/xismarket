<?php

namespace App\Http\Controllers\Storefront\Concerns;

use Illuminate\Support\Str;

/**
 * Builds the raw (unhashed) identity array a signed-in shopper contributes to a
 * Meta event's user_data. MetaConversionsService hashes it before it leaves the
 * app; guests contribute nothing here (only cookies/IP/UA from the request).
 */
trait BuildsMetaUserIdentity
{
    /** @return array{email?:?string,phone?:?string,first_name?:?string,last_name?:?string} */
    protected function customerIdentity(): array
    {
        $customer = auth('customer')->user();
        if (! $customer) {
            return [];
        }

        return [
            'email' => $customer->email,
            'phone' => $customer->phone,
            'first_name' => Str::before((string) $customer->name, ' '),
            'last_name' => trim(Str::after((string) $customer->name, ' ')) ?: null,
        ];
    }
}
