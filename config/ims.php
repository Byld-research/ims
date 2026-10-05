<?php

return [

    /*
    | SKU validation (SPEC 10). Until the numbering scheme is agreed, any non-empty
    | string up to 40 characters is accepted. Set SKU_PATTERN to a full PCRE
    | pattern, e.g. "/^[A-Z]{3}-\d{5}$/", and SKU_PATTERN_HINT to explain it.
    */
    'sku_pattern' => env('SKU_PATTERN'),
    'sku_pattern_hint' => env('SKU_PATTERN_HINT'),

    /*
    | Units of measure offered as suggestions on the item form. Free text is allowed.
    */
    'uoms' => ['pc', 'set', 'pair', 'l', 'm', 'kg', 'box', 'roll'],

    /*
    | Development seed accounts (DevUserSeeder). Never used in production.
    */
    'seed' => [
        'admin_email' => env('SEED_ADMIN_EMAIL', 'admin@bpc.test'),
        'admin_password' => env('SEED_ADMIN_PASSWORD'),
        'manager_password' => env('SEED_MANAGER_PASSWORD'),
    ],

];
