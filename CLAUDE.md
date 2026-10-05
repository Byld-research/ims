# BPC Inventory System

`SPEC.md` is the authoritative specification. Read it before changing behaviour; if something is not in it, it is out of scope. `IMS_USA_documentation.md` and `IMS_USA_spec_mvp.md` are superseded background documents.

## Stack and local setup

Laravel 13, PHP 8.2+, MariaDB (InnoDB, utf8mb4_unicode_ci), Breeze Blade, Tailwind, Alpine, Pest.

- App database `ims`, test database `ims_test`, user `ims` / `ims` on 127.0.0.1 (Homebrew MariaDB: `brew services start mariadb`).
- `php artisan migrate:fresh --seed` — sites, reason codes, categories, plus dev users from `SEED_*` env vars (`admin@bpc.test`, `manager.bpc001@bpc.test`, `manager.bpc002@bpc.test`).
- `php artisan db:seed --class=DemoDataSeeder` — demo catalogue: 20 items, 5 suppliers, 3 machine types, 9 work centres.
- `php artisan test` — the suite runs against MariaDB, not SQLite: triggers, CHECK constraints and row locking are part of what is tested.
- `./vendor/bin/pint` before committing.

## Rules that are easy to break

- **All stock mutations go through `App\Services\StockService`.** `stocks.qty` and `stocks.avg_cost` are not fillable on purpose.
- **`stock_transactions` is append-only**, enforced by the model and by database triggers. Corrections are compensating adjustments.
- **No floats for money or quantities.** Use `App\Support\Decimal`; it rounds half-up. Raw `bcdiv`/`bcmul` truncate and will break the 4.6667 acceptance case.
- **Authorisation through policies only.** A manager writes only where `site_id` is their own (`User::canWriteSite`). The single exception is a transfer, authorised against the receiving site (`StockPolicy::transfer`).
- **Statuses, types and roles are enums in `App\Enums`.** `PurchaseOrderStatus::transitions()` mirrors the SPEC 5.3 diagram.
- **The current site comes from `App\Support\CurrentSite`.** Never assume a site silently. `null` means the administrators' consolidated view.
- **Nothing is hard-deleted.** Records are deactivated (`is_active`).
- **Display formatting goes through `App\Support\Format`.** Timestamps are stored in UTC and shown in the site's time zone.
- **New screens get a `config/navigation.php` entry.** It appears once its route exists.
- **List views support `?export=csv` via `App\Support\CsvExport`.** Every list needs one (SPEC 7).
- **Pick items with `<x-item-picker>`**, backed by `items.search`.
- **`tests/Feature/WriteRoutesTest.php` sweeps every authenticated write route as an operator.** A new route parameter needs a fixture there.
- **Every business rule in SPEC 5 and every acceptance criterion in SPEC 13 needs a feature test.**

## Build progress (SPEC 12)

- [x] Stage 1: schema, enums, models, auth, roles, policies, site selector, layout
- [x] Stage 2: categories, items, suppliers, supplier items, machine types, parts lists (CSV import), work centres, stock list
- [ ] Stage 3: StockService, adjustments, stock list, item detail, bulk level editor
- [ ] Stage 4: purchase orders, transitions, receiving
- [ ] Stage 5: issues and transfers
- [ ] Stage 6: stock counts
- [ ] Stage 7: dashboard, kanban view, CSV exports
- [ ] Stage 8: daily digest, audit log, hardening
