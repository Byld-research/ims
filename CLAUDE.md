# BPC Inventory System

`SPEC.md` is the authoritative specification. Sites: **BPC001 = Georgia, BPC002 = Colorado**. Read it before changing behaviour; if something is not in it, it is out of scope. `IMS_USA_documentation.md` and `IMS_USA_spec_mvp.md` are superseded background documents.

## Stack and local setup

Laravel 13, PHP 8.2+, MariaDB (InnoDB, utf8mb4_unicode_ci), Breeze Blade, Tailwind, Alpine, Pest.

- App database `ims`, test database `ims_test`, user `ims` / `ims` on 127.0.0.1 (Homebrew MariaDB: `brew services start mariadb`).
- `php artisan migrate:fresh --seed` — sites, reason codes, categories, the real machine register (8 types, 10 US machines), plus dev users from `SEED_*` env vars (`admin@bpc.test`, `manager.bpc001@bpc.test`, `manager.bpc002@bpc.test`).
- `php artisan db:seed --class=DemoDataSeeder` — demo catalogue: 22 items, 5 suppliers, parts lists for types C, A and W.
- `php artisan test` — the suite runs against MariaDB, not SQLite: triggers, CHECK constraints and row locking are part of what is tested. The `Concurrency` suite (`tests/Concurrency`) starts real parallel PHP processes against `ims_test`, commits data and rebuilds the database afterwards; run it alone with `./vendor/bin/pest --testsuite=Concurrency`.
- `./vendor/bin/pint` before committing.

## Terminology

Use the terms from SPEC 1a exactly. *Machine type* is a letter (C = Truss Saw) and holds the parts list. *Machine* is one physical unit with a SKU like `004C`, a revision and a current site. Stock is issued to machines (`ISSUE_MACHINE`, `machine_id`). There is no "work centre". Type C (003C rev 1.0 at BPC001 Georgia, 004C rev 2.0 at BPC002 Colorado) is the reference case for tests and demo data.

## Rules that are easy to break

- **All stock mutations go through `App\Services\StockService`.** `stocks.qty` and `stocks.avg_cost` are not fillable on purpose. New movement types add a public method that calls `post()` inside `DB::transaction(..., StockService::ATTEMPTS)`.
- **Lock order is always: purchase order, then its lines, then stock rows** (see `PurchaseOrderService`). Keep it when adding movements, or deadlocks follow.
- **Concurrency is tested for real, not only in Pest.** Pest runs inside one transaction, so it cannot test row locks. After touching locking, run parallel writers against the dev database, then run `php artisan ims:verify-stock`. The connection uses READ COMMITTED on purpose (see `config/database.php`).
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
- [x] Stage 2: categories, items, suppliers, supplier items, machine types, parts lists (CSV import, per revision), machine register, stock list
- [x] Stage 3: StockService, adjustments (incl. opening balances), stock list levels and filters, item movement history, bulk level editor, `ims:verify-stock`
- [x] Stage 4: purchase orders, numbering, transitions, partial receipts, close short, over-receipt warning, on-order quantities, real-process concurrency tests
- [ ] Stage 5: issues and transfers
- [ ] Stage 6: stock counts
- [ ] Stage 7: dashboard, kanban view, CSV exports
- [ ] Stage 8: daily digest, audit log, hardening
