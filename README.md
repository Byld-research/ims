# BPC Inventory

Inventory for the BPC manufacturing sites in Georgia (BPC001) and Colorado (BPC002): how much of what is where, and what is running out. Spare parts, wear parts, consumables and tools; purchase orders, issues to machines, transfers between sites, cycle counts, and a daily digest.

**[SPEC.md](SPEC.md) is the authoritative specification.** [CLAUDE.md](CLAUDE.md) lists the rules developers must not break.

## Stack

Laravel 13 on PHP 8.2+, MariaDB 10.6+ (InnoDB), Blade with Tailwind and Alpine.js, Pest for tests.

## Local setup

```sh
brew install php composer mariadb node && brew services start mariadb
mariadb -e "CREATE DATABASE ims CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
            CREATE DATABASE ims_test CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
            CREATE USER 'ims'@'localhost' IDENTIFIED BY 'ims';
            GRANT ALL ON ims.* TO 'ims'@'localhost'; GRANT ALL ON ims_test.* TO 'ims'@'localhost';
            GRANT ALL ON ims_restore_test.* TO 'ims'@'localhost';"
composer install && npm install && npm run build
cp .env.example .env && php artisan key:generate   # then set SEED_ADMIN_PASSWORD and SEED_MANAGER_PASSWORD
php artisan migrate --seed                          # sites, reason codes, categories, machine register, dev users
php artisan db:seed --class=DemoDataSeeder          # optional: demo catalogue, history, orders, counts
php artisan serve
```

Development accounts: `admin@bpc.test`, `manager.bpc001@bpc.test` (Georgia), `manager.bpc002@bpc.test` (Colorado), with the passwords from `.env`.

## Tests

```sh
php artisan test                                   # all suites, against MariaDB
./vendor/bin/pest --testsuite=Concurrency          # real parallel processes: locks, double posting, numbering
```

The suites run against MariaDB on purpose: triggers, CHECK constraints and row locks are part of what is tested.

## Production

1. Environment: `APP_ENV=production`, `APP_DEBUG=false`, `APP_URL=https://…`, database credentials, SMTP (`MAIL_*`), `SESSION_LIFETIME=60`, `SESSION_SECURE_COOKIE=true`, `OPS_EMAIL`, `BACKUP_PATH` (a mounted volume off the database server), `ADMIN_DIGEST_HOUR` and `ADMIN_DIGEST_TIMEZONE`. Leave `SEED_*` empty.
2. Deploy: `composer install --no-dev -o && npm ci && npm run build && php artisan migrate --force && php artisan db:seed --force && php artisan optimize`.
3. Cron, as the web user:
   ```
   * * * * * cd /path/to/app && php artisan schedule:run >> /dev/null 2>&1
   ```
4. Create the first administrator with `php artisan tinker` (`User::create([...'role' => 'ADMIN'])`); everyone else under **Admin → Users**.
5. Before go-live, prove the backups: `php artisan ims:backup && php artisan ims:restore-test`.

### Scheduled jobs

| Job | When | What |
|---|---|---|
| `ims:send-digests` | hourly | each site's digest at its local `digest_hour`; administrators' all-sites digest at `ADMIN_DIGEST_HOUR` |
| `ims:backup` | 07:00 UTC | gzipped dump incl. triggers; older than `BACKUP_KEEP_DAYS` removed |
| `ims:verify-stock` | 07:30 UTC | every stock row against a ledger replay; failure mails `OPS_EMAIL` |

### Restoring a backup

```sh
gunzip -c storage/app/backups/ims-YYYYMMDD-HHMMSS.sql.gz | mariadb -u ims -p ims
php artisan ims:verify-stock
```
