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
        'logo' => '/images/logos/buildtech.png',

        /*
        | The seller identity printed on quotations. Every key is optional:
        | anything left blank is simply left off the printed page.
        */
        'company' => [
            'name' => 'BUILD TECH SUPPORTS',
            'address' => [
                'Ground Floor,59/4699,4700',
                'Panchavadi Arcade,M N Road',
                'Pushpa Jn,Calicut-673002',
            ],
            'phone' => '0495 4856900',
            'mobile' => '9744729000',
            'udyam' => 'UDYAM-KL-08-0081330 (Micro/Mfgr)',
            'gstin' => '32AAUFB0851A1ZX',
            'state' => ['name' => 'Kerala', 'code' => '32'],
            'email' => 'buildtechgroupclt@gmail.com',
            'website' => null,
            'bank' => [
                'name' => 'Axis Bank',
                'account' => '921020054975428',
                'branch' => 'Kaliai Road,Kozhikode',
                'ifsc' => 'UTIB0001908',
                'branch_ifsc' => 'Kaliai Road,Kozhikode & UTIB0001908',
            ],
            'declaration' => 'We declare that this invoice shows the actual price of the goods described and that all particulars are true and correct.',
            'quotation_title' => 'PROFORMA INVOICE',
            'footer' => 'This is a Computer Generated Invoice',
        ],
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

            // Statutory details are per company, so this brand states its own
            // rather than inheriting the default brand's.
            'company' => [
                'name' => 'KAPTECH ENGINEERING',
                'address' => [],
                'phone' => null,
                'mobile' => null,
                'udyam' => null,
                'gstin' => null,
                'state' => ['name' => null, 'code' => null],
                'email' => null,
                'bank' => ['name' => null, 'account' => null, 'branch' => null, 'ifsc' => null, 'branch_ifsc' => null],
            ],
        ],

        'longlast.kaptechengineering.com' => [
            'name' => 'Longlast',
            'subtitle' => 'Engineering',
            'logo' => '/images/logos/longlast.png',

            'company' => [
                'name' => 'LONGLAST',
                'address' => [],
                'phone' => null,
                'mobile' => null,
                'udyam' => null,
                'gstin' => null,
                'state' => ['name' => null, 'code' => null],
                'email' => null,
                'bank' => ['name' => null, 'account' => null, 'branch' => null, 'ifsc' => null, 'branch_ifsc' => null],
            ],
        ],

    ],

];
