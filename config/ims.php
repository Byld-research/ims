<?php

return [

    /*
    | Development seed accounts (DevUserSeeder). Never used in production.
    */
    'seed' => [
        'admin_email' => env('SEED_ADMIN_EMAIL', 'admin@bpc.test'),
        'admin_password' => env('SEED_ADMIN_PASSWORD'),
        'manager_password' => env('SEED_MANAGER_PASSWORD'),
    ],

];
