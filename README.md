# BPC Inventory

Inventory management for the BPC manufacturing sites in the United States: **BPC001 Georgia** and **BPC002 Colorado**. In production at **https://ims.byldinc.com**.

It answers two questions for the site managers:

1. **How much of what do we have, and at which site?**
2. **What is running out?**

It covers spare parts, wear parts, consumables and tools for the production machines. It is deliberately not an ERP, MRP or accounting system.

![Dashboard for BPC002 Colorado](docs/user-guide/images/dashboard.png)

## Documentation

| Document | What it covers | For |
|---|---|---|
| **[Project documentation](docs/PROJECT.md)** | purpose, scope, roles, key terms, processes with diagrams, functions, rules, decisions, production environment, history, status and risks | owner, management, new team members |
| **[User guide](docs/USER-GUIDE.md)** | what each role does and how: click sequences and screenshots for operators, managers and administrators | everyone using the application |
| [User guide chapters](docs/user-guide/) | one chapter per task, in full detail (11 chapters) | everyone |
| **[FAQ](docs/FAQ.md)** | the 25 questions users ask most | everyone |
| **[Architecture and technical guide](docs/ARCHITECTURE.md)** | stack, layout, data model, stock ledger, concurrency, authorisation, testing, configuration, how-to recipes | developers |
| [Deployment](docs/deployment.md) | the production server, updates, email, backups, checks | developers, system owner |
| **[Instructions for AI assistants](AGENTS.md)** | how another AI model should continue the work: rules, definition of done, production cautions | AI coding assistants |
| [Specification](SPEC.md) | the authoritative rules and acceptance criteria | developers, project owner |

## Roles in one line each

- **Operator**: reads stock, items, orders and machines at both sites; changes nothing.
- **Manager**: runs their own site: issues, receipts, transfers in, adjustments, counts, minimum levels, purchase orders; reads the other site.
- **Administrator**: everything at every site, plus users, sites, reason codes, the machine register and the audit log.

## For developers: quick start

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
php artisan test                                    # Unit, Feature and Concurrency suites, against MariaDB
```

Deploy a new version to production: `scripts/deploy.sh` (see [docs/deployment.md](docs/deployment.md)). Stack: Laravel 13, PHP 8.3+, MariaDB, Blade, Tailwind, Alpine.js, Pest. Details: [docs/ARCHITECTURE.md](docs/ARCHITECTURE.md).
