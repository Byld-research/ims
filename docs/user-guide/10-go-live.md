# 10. Go-live checklist

[← Back to README](../../README.md)

For the project team. Each step lists who is typically responsible; adjust the owners in review. The order matters: later steps depend on earlier ones.

| # | Step | Owner | Done when |
|---|---|---|---|
| 1 | **Agree the item SKU numbering scheme** and give the pattern to IT (`SKU_PATTERN`) | Project owner | Pattern set; new items are checked against it |
| 2 | **Production server**: HTTPS address, database, email (SMTP), backup location off the database server, scheduler (cron) | IT | Application reachable; a test email arrives |
| 3 | **Prove the backups**: run a backup and the restore test | IT | `ims:restore-test` reports *passed* |
| 4 | **First administrator** account; then manager and operator accounts per site, digest switched on for managers | IT, then administrator | Everyone can log in at their site |
| 5 | **Catalogue**: items with SKU, name, category, unit, criticality; suppliers with prices and pack sizes | Technical data owner | Items in the stock list; suppliers linked |
| 6 | **Parts lists** per machine type, starting with **C · Truss Saw** (003C rev 1.0, 004C rev 2.0), imported from the spreadsheets via CSV | Technical data owner | Each machine page shows its parts |
| 7 | **Minimum levels and bins** per site, from the suggested quantities in the spare parts spreadsheets; mark kanban items | Site manager | *Min levels & bins* filled; no class A item without a level |
| 8 | **Opening stock count** per site in an agreed time window, entered as *Opening balance* with the last known price | Site manager | Every item on the shelf has a quantity and a cost |
| 9 | **Short training**: issuing in three steps, receiving, transfers, the dashboard | Site manager | Managers have issued and received once each |
| 10 | **Go live** at the first site; the second follows | Sponsor | Daily issuing and receiving happen in the system |

## Things to decide before step 5

- **Catalogue import.** The application has a CSV import for parts lists, not yet for items. A few hundred items can be entered with the form; for a larger catalogue, ask IT to load the spreadsheet once, or ask for an item import to be added.
- **Opening costs.** Entering the last known price makes stock values usable from day one; entering no cost is not possible. Record the source of each price in the note.
- **Which items get a minimum level.** At least every class A item and every consumable (project success criterion).
- **Who keeps the data current** at each site after go-live (records only stay right if every issue is entered).

## The first weeks

- Managers open the dashboard daily, or rely on the daily digest.
- The first monthly class A count after four weeks shows how accurate the records are; differences point to issues that were not entered.
- After about three months, compare consumption per machine with the minimum levels and adjust them.
