# Instructions for AI coding assistants

This file is for any AI model or coding agent (Claude, Codex, Gemini, Cursor, Copilot, …) continuing work on the **BPC Inventory System**. Read it fully before changing anything. `CLAUDE.md` imports this file, so there is one set of rules.

## 1. What this is

An inventory management web application for two US manufacturing sites of BPC: **BPC001 = Georgia, BPC002 = Colorado**. It answers two questions for site managers: *how much of what do we have, and where?* and *what is running out?* It is in production at **https://ims.byldinc.com** and holds real data.

## 2. Read in this order

1. **[SPEC.md](SPEC.md)**: the authoritative specification. If a behaviour is not in it, it is out of scope; section 11 lists what must **not** be built. Change history at the end.
2. **[docs/ARCHITECTURE.md](docs/ARCHITECTURE.md)**: stack, layout, data model, ledger, locking, testing, how-to recipes.
3. **[docs/PROJECT.md](docs/PROJECT.md)**: purpose, processes, decisions and their reasons, status, risks.
4. **[docs/deployment.md](docs/deployment.md)**: the production server.
5. **[docs/API.md](docs/API.md)** and `resources/api/openapi.yaml`: the read-only API v1.
6. User documentation, which you must keep in sync: [docs/USER-GUIDE.md](docs/USER-GUIDE.md), [docs/user-guide/](docs/user-guide/), [docs/FAQ.md](docs/FAQ.md).

`IMS_USA_documentation.md` and `IMS_USA_spec_mvp.md`, if present, are superseded background documents.

## 3. Working with the owner

- The owner writes **Polish**. Answer in Polish. The application interface, code, comments, commit messages and documentation stay in **English**.
- The owner is not a developer. Explain technical terms in plain words, keep answers short, and lead with the result.
- **Interface wording:** use plain, industry-standard terms and explain jargon. Before renaming anything visible, offer the options in a short comparison table and let the owner choose. They prefer reading the options and answering in their own words over multiple-choice prompts. Accepted names so far: *Inventory*, *Two-bin items* (not *Kanban*), *Stock counts*, *Min levels & locations*, *Location* (not *bin*), criticality *High / Normal / Low*, status *Below min*.
- **Commit, push and deploy only when asked** ("commit", "wypchnij", "wdróż"). Never deploy uncommitted work.
- For a feature request, confirm the open design decision (e.g. which formula, which option) before building, then build it completely: code, tests, SPEC, docs, screenshots.
- Report problems plainly, including your own mistakes, with what was affected and for how long.

## 4. Terminology

Use the terms from SPEC 1a exactly. *Machine type* is a letter (C = Truss Saw) and holds the parts list. *Machine* is one physical unit with a SKU such as `004C` (serial + letter), a revision and a current site. Stock is issued to machines (`ISSUE_MACHINE`, `machine_id`). There is no "work centre". The interface shows plain words while the database keeps the original names: `stocks.is_kanban` = *Two-bin item*, `stocks.bin` = *Location*, route `kanban.index` = `/two-bin`. Type C (003C rev 1.0 at BPC001, 004C rev 2.0 at BPC002) is the reference case for tests and demo data.

## 5. Stack and local set-up

Laravel 13, PHP 8.3+ (production 8.5), MariaDB (InnoDB, utf8mb4_unicode_ci), Breeze Blade, Tailwind 3, Alpine.js, Vite, Pest.

- App database `ims`, test database `ims_test`, user `ims` / `ims` on 127.0.0.1 (Homebrew MariaDB: `brew services start mariadb`).
- `php artisan migrate:fresh --seed`: sites, reason codes, categories, the real machine register (8 types, 10 US machines), plus dev users from `SEED_*` env vars (`admin@bpc.test`, `manager.bpc001@bpc.test`, `manager.bpc002@bpc.test`).
- `php artisan db:seed --class=DemoDataSeeder`: demo catalogue, opening balances, consumption history, orders and counts. **Never run it in production.**
- `php artisan test`: Unit, Feature and Concurrency suites against MariaDB, never SQLite (triggers, CHECK constraints and row locks are part of the behaviour). The Concurrency suite starts real parallel PHP processes against `ims_test`, commits data and rebuilds the database; run it alone with `./vendor/bin/pest --testsuite=Concurrency`.
- `./vendor/bin/pint` before committing.

## 6. Rules that are easy to break

- **All stock mutations go through `App\Services\StockService`.** `stocks.qty` and `stocks.avg_cost` are not fillable on purpose. New movement types add a public method that calls `post()` inside `DB::transaction(..., StockService::ATTEMPTS)`.
- **Lock order is always: purchase order, then its lines, then stock rows** (see `PurchaseOrderService`). Several stock rows are locked in site id order (see `StockService::transfer`). Keep both rules when adding movements, or deadlocks follow; `tests/Concurrency` proves it.
- **Concurrency is tested for real, not only in Pest.** Pest runs inside one transaction, so it cannot test row locks. After touching locking, run the Concurrency suite and `php artisan ims:verify-stock`. The connection uses READ COMMITTED on purpose (`config/database.php`; MariaDB snapshot isolation raises error 1020 otherwise).
- **`stock_transactions` is append-only**, enforced by the model and by database triggers. Corrections are compensating adjustments.
- **No floats for money or quantities.** Use `App\Support\Decimal`; it rounds half-up. Raw `bcdiv`/`bcmul` truncate and will break the 4.6667 acceptance case.
- **Authorisation through policies only.** A manager writes only where `site_id` is their own (`User::canWriteSite`). The single exception is a transfer, authorised against the receiving site (`StockPolicy::transfer`).
- **Statuses, types and roles are enums in `App\Enums`.** `PurchaseOrderStatus::transitions()` mirrors the SPEC 5.3 diagram.
- **The current site comes from `App\Support\CurrentSite`.** Never assume a site silently. `null` means the administrators' consolidated view.
- **Master data models use the `Auditable` trait.** New master data models need it too; list ledger-owned or secret attributes in `$auditExclude`.
- **Nothing is hard-deleted.** Records are deactivated (`is_active`).
- **Display formatting goes through `App\Support\Format`.** Timestamps are stored in UTC and shown in the site's time zone.
- **New screens go into a section of `config/navigation.php`** (Stock, Purchasing, Machines, Admin) or into `actions` (stock movements). Don't add top-level entries. A link appears once its route exists and the user passes its `can`. Page headers keep only page-level actions (Export CSV, New …).
- **Charts:** single-series bars use `<x-bar-list>` in one hue (#2a78d6); status colours always come with an icon and a word. After changing a chart or layout, render the page and look at it.
- **List views support `?export=csv` via `App\Support\CsvExport`.** Every list needs one.
- **Pick items with `<x-item-picker>`**, backed by `items.search`.
- **`tests/Feature/WriteRoutesTest.php` sweeps every authenticated write route as an operator.** A new route parameter needs a fixture there. Routes like `/purchase-orders/quick` must be registered before `/purchase-orders/{purchaseOrder}`.
- **Every business rule in SPEC 5 and every acceptance criterion in SPEC 13 needs a feature test.** Name the criterion in the test title.
- **The API v1 only reads** (SPEC 7a). Keep every `routes/api.php` route GET. Scope site-bound data with `Api\V1\Controller::siteId()` / `ensureInScope()`. A new field goes into its Resource, `resources/api/openapi.yaml` and `docs/API.md` together. Code that runs on API requests must not assume `Auth::user()` is a `User`: it can be an `ApiClient`.
- **Blade:** use block `@php … @endphp`; an inline `@php(...)` before a later block gets swallowed. Don't reuse a view variable name that the page already uses further down (e.g. `$orders` on the dashboard).

## 7. Definition of done for a change

1. **SPEC.md** updated first when behaviour changes: the rule, a new acceptance criterion, a change-history line with the next version number.
2. Code following the rules above.
3. Feature tests (and Concurrency tests when locking changes). `php artisan test` passes; `./vendor/bin/pint` run.
4. **User documentation** updated for any visible change: [docs/USER-GUIDE.md](docs/USER-GUIDE.md), the matching chapter in [docs/user-guide/](docs/user-guide/), [docs/FAQ.md](docs/FAQ.md) if an answer changes, and `11-messages.md`, which quotes messages word for word.
5. **Screenshots** retaken for changed screens (Playwright, 1280 px wide, from the local demo data), saved in `docs/user-guide/images/`. Look at each one before using it.
6. [docs/ARCHITECTURE.md](docs/ARCHITECTURE.md) or [docs/PROJECT.md](docs/PROJECT.md) updated when the architecture, a decision or the status changes.
7. Commit message: an imperative summary line, a body saying what and why, ending with the attribution line your tool requires.

## 8. Production

- Server: OVH VPS `144.217.90.113`, user `ubuntu`, SSH key `~/.ssh/vps_ims` with `-o IdentitiesOnly=yes`. App in `/var/www/ims`.
- **Deploy only with `scripts/deploy.sh`**, from a clean, committed `main`, and only when the owner asks. It runs the tests, builds, uploads, migrates in a few seconds of maintenance mode and reloads PHP-FPM.
- **Never** run seeders other than the production ones, `migrate:fresh`, or anything destructive against production. Production data is real.
- **`.env` on the server** must stay `ubuntu:www-data` mode 640. `sed -i` and editors replace the file and drop the group, and the app then runs without its settings. After any edit: `sudo chgrp www-data .env && chmod 640 .env && sudo -u www-data php artisan optimize && sudo systemctl reload php8.5-fpm`. Back it up first.
- SSH password login stays enabled (the owner's decision); bots fill sshd's `MaxStartups`, so single SSH connections can be dropped. Retry; `deploy.sh` already uses one shared connection with retries.
- Email: Microsoft 365 Direct Send; it reaches **@byldinc.com** only. Outbound connections prefer IPv4 (`/etc/gai.conf`) because the VPS's IPv6 address is on a Spamhaus list.
- Secrets (database password, `APP_KEY`) exist only in the server's `.env`. Never copy them into the repository, commits or chat.

## 9. Known pitfalls from earlier work

- zsh: arrays are 1-based, and `LINES` is a reserved variable.
- SQL: `lines` is a reserved word in MariaDB; don't use it as an alias.
- `Route::redirect` registers every HTTP method and breaks the write-route sweep; use a GET-only closure.
- `GROUP BY` with a bound expression fails under strict mode; group by the alias instead.
- Sorting criticality alphabetically gives HIGH, LOW, NORMAL; use `Criticality::orderSql()`.
- An Alpine-driven hidden input is not updated yet in the same event; pass `$event.detail` values directly.
- Playwright for screenshots needs `PLAYWRIGHT_BROWSERS_PATH` pointing at an installed browser; take screenshots against `php artisan serve --port=8765` with the demo data.

## 10. Build progress

All eight stages of SPEC 12 are complete, plus, after go-live, the quick order from the dashboard (SPEC 5.3a) with orders in progress per item, and the read-only API v1 (SPEC 7a). SPEC version 1.10. Values still to come from the business (SPEC 10): SKU numbering scheme, parts lists per machine type, initial minimum levels, opening stock count.
