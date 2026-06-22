<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Default Branding
    |--------------------------------------------------------------------------
    |
    | Used when the incoming request host is not listed under "domains" below.
    | A null "logo" falls back to the built-in AppLogoIcon component.
    |
    */

    'default' => [
        'name' => 'Build Tech',
        'subtitle' => 'Supports',
        'logo' => null,
    ],

    /*
    |--------------------------------------------------------------------------
    | Per-Domain Branding
    |--------------------------------------------------------------------------
    |
    | Map a request host to its branding. The "logo" is a public web path
    | (relative to the "public" directory) for an image you drop in there.
    |
    */

    'domains' => [

        'crm.kaptechengineering.com' => [
            'name' => 'Kaptech CRM',
            'subtitle' => 'Engineering',
            'logo' => '/images/logos/crm.png',
        ],

        'longlast.kaptechengineering.com' => [
            'name' => 'Longlast',
            'subtitle' => 'Engineering',
            'logo' => '/images/logos/longlast.png',
        ],

    ],

];
