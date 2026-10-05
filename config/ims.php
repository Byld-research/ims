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
    | Daily digest (SPEC 5.9). Each site sends at its own sites.digest_hour, local time.
    | Administrators have no site; they get one all-sites digest at this hour and time zone.
    */
    'admin_digest' => [
        'hour' => (int) env('ADMIN_DIGEST_HOUR', 7),
        'timezone' => env('ADMIN_DIGEST_TIMEZONE', 'America/New_York'),
    ],

    /*
    | Operations (SPEC 9a). Where nightly backups go, how long they are kept, and who hears
    | about a failed nightly check.
    */
    'backup' => [
        'path' => env('BACKUP_PATH') ?: storage_path('app/backups'),
        // Scratch database that ims:restore-test loads a backup into, then drops.
        'restore_test_database' => env('BACKUP_RESTORE_TEST_DATABASE', 'ims_restore_test'),
        'keep_days' => (int) env('BACKUP_KEEP_DAYS', 30),
    ],
    'ops_email' => env('OPS_EMAIL'),

    /*
    | Development seed accounts (DevUserSeeder). Never used in production.
    */
    'seed' => [
        'admin_email' => env('SEED_ADMIN_EMAIL', 'admin@bpc.test'),
        'admin_password' => env('SEED_ADMIN_PASSWORD'),
        'manager_password' => env('SEED_MANAGER_PASSWORD'),
    ],

];
