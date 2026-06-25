<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Default storefront
    |--------------------------------------------------------------------------
    |
    | When set to a store slug, the root URL ("/") sends visitors who are not
    | logged into the admin straight to that store's storefront instead of the
    | login page. Leave null to keep the default admin-first behaviour.
    |
    */

    'default_store' => env('STOREFRONT_DEFAULT_STORE'),

];
