# Deployment: production VPS

[← Back to README](../README.md)

Production runs on an OVHcloud VPS. This page records how it was set up and how to update it.

| | |
|---|---|
| Server | OVHcloud VPS `vps-7edfab5f`, `144.217.90.113`, Ubuntu 26.04 LTS, 4 vCPU, 8 GB RAM |
| Address | `https://ims.byldinc.com` (A record in the OVH zone of `byldinc.com`) |
| Stack | nginx 1.28, PHP 8.5 (FPM), MariaDB 11.8, Composer, certbot |
| App | `/var/www/ims`, owned `ubuntu:www-data`; PHP-FPM runs as `www-data` |
| Database | `ims` on localhost, user `ims`; its password exists only in `/var/www/ims/.env` |
| Backups | `/var/backups/ims`, nightly, 30 days |
| Access | SSH key login as `ubuntu` (`ssh -i ~/.ssh/vps_ims -o IdentitiesOnly=yes ubuntu@144.217.90.113`); password login also enabled, protected by fail2ban |

## Why not the OVH Cloud Web hosting

The Cloud Web 1 plan offered PHP 8.0 at most and MySQL 5.6, both out of support; the application needs PHP 8.3+ and MySQL 8 / MariaDB 10.6+. OVH also announced the plan's retirement.

## What was set up

1. **Packages**: `nginx mariadb-server php8.5-fpm php8.5-{cli,mysql,bcmath,intl,mbstring,xml,curl,zip,gd} composer certbot python3-certbot-nginx fail2ban rsync`.
2. **Firewall** (ufw): only SSH and HTTP/HTTPS open. **fail2ban** bans an address for an hour after 5 failed SSH logins in 10 minutes. Ubuntu's unattended upgrades apply security fixes.
3. **Database**: `ims` (utf8mb4_unicode_ci) and user `ims@localhost` with a generated 32-character password; the same user may create `ims_restore_test` for the restore test.
4. **Application**: code uploaded with rsync; `.env` with `APP_ENV=production`, `APP_DEBUG=false`, `SESSION_LIFETIME=60`, `SESSION_SECURE_COOKIE=true`, `BACKUP_PATH=/var/backups/ims`; `migrate` and `db:seed` loaded the sites, categories, reason codes and machine register only: no demo data, no development accounts.
5. **nginx**: site `/etc/nginx/sites-available/ims`, document root `public/`, dotfiles denied, built assets cached for a year.
6. **Scheduler**: `/etc/cron.d/ims` runs `php artisan schedule:run` every minute as `www-data` (digests hourly per site, backup 07:00 UTC, ledger check 07:30 UTC).
7. **HTTPS**: `sudo certbot --nginx -d ims.byldinc.com --redirect` once the DNS record points to the VPS; certbot renews automatically.

## Creating the first administrator

Run from your own terminal (`-t` gives a terminal, so the password prompt is hidden):

```sh
ssh -t -i ~/.ssh/vps_ims -o IdentitiesOnly=yes ubuntu@144.217.90.113 \
  'cd /var/www/ims && sudo -u www-data php artisan ims:create-admin admin@byldinc.com'
```

The same command resets an administrator's password.

## Updating to a new version

From the project folder, with everything committed:

```sh
scripts/deploy.sh
```

It runs the tests, builds the assets, uploads the code, installs dependencies, migrates the database inside a short maintenance window, refreshes caches and reloads PHP-FPM. The server's `.env`, storage and backups are left alone.

## Checks

```sh
ssh -i ~/.ssh/vps_ims -o IdentitiesOnly=yes ubuntu@144.217.90.113 'cd /var/www/ims && sudo -u www-data php artisan ims:verify-stock'
ssh -i ~/.ssh/vps_ims -o IdentitiesOnly=yes ubuntu@144.217.90.113 'cd /var/www/ims && sudo -u www-data php artisan ims:restore-test'
```

## Still to do

- **Email (SMTP)**: `MAIL_*` in `.env` are set to `log`; the daily digest and password reset emails need real SMTP settings.
- **Off-server backups**: the nightly dumps are on the same disk as the database. Enable OVH's automated VPS backup, or copy `/var/backups/ims` elsewhere.
- **OPS_EMAIL** in `.env`, so failed nightly jobs are reported.
