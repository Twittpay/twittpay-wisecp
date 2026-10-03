<?php

return [
    'meta'     => [
        'name'    => 'TwittPay',
        'version' => '1.0',
        'logo'    => 'logo.png',
    ],
    'settings' => [
        // Both are filled in from the admin panel: Settings -> Payment Gateways.
        'api_key'              => '',
        'base_url'             => '',

        // Used only when the invoice is not already in BDT. 1 USD = this many BDT.
        'currency_rate'        => '120',

        'commission_rate'      => '0',

        // 0 = leave the invoice in its own currency and let the module convert.
        // If you have BDT set up in WISECP, put its currency id here instead and
        // the exact amount is sent with no conversion at all.
        'force_convert_to'     => 0,

        'accepted_countries'   => [],
        'unaccepted_countries' => [],
    ],
];
