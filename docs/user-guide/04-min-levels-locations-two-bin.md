# 4. Min levels, locations and two-bin items

[← Back to README](../../README.md)

Minimum levels, shelf locations and two-bin settings are kept **per site**: the same item can have a minimum of 2 in Colorado and none in Georgia.

## Setting them in bulk

**Stock → Min levels & locations** (managers for their own site, administrators for any site).

![Min levels and locations for Colorado](images/levels.png)

| Column | Meaning |
|---|---|
| **Min level** | Below this the item is flagged *Below min*. **Empty or 0 turns the alert off.** |
| **Location** | Where the item is stored: free text, e.g. shelf `CO-SP01` |
| **Two-bin** | Tick for low-value, regularly used items kept in two bins (see below) |
| **Qty per bin** | How many units one bin holds. Required for two-bin items. |

- **Save levels** saves the rows on the current page (50 per page). Use the filters (**No level set**, **Two-bin only**, category, search) to work through the catalogue.
- If one row is wrong, nothing is saved and the row is marked.
- Rows you did not touch for items never stocked at this site create nothing.

## When an item is flagged

| Item type | Flagged when |
|---|---|
| Normal item with a minimum | quantity **below** the minimum |
| Two-bin item | quantity **at or below one bin** (the minimum level is ignored) |

Quantities on open orders are shown next to the flag but never clear it: the part is not on the shelf yet.

## Two-bin items

The **two-bin system** is a simple way to keep cheap, regularly used items (filters, grease, fasteners) in stock without watching numbers:

1. The item is kept in **two bins** on the shelf.
2. Parts are taken from the first bin.
3. When the first bin is **empty**, that is the signal to **reorder**. The second bin covers the time until the delivery arrives.
4. The delivery refills the empty bin, which goes behind the other one.

In the system, a two-bin item has a **quantity per bin** instead of a minimum level, and it is flagged **Refill** as soon as one bin or less is left. (The method is also known as *two-bin kanban*.)

**Stock → Two-bin items** lists the two-bin items at your site, those needing a refill first, then by bins left.

![Two-bin items](images/two-bin.png)

Stock is still recorded in the item's unit; *Bins left* is only a reading aid.

## Where the first minimum levels come from

At go-live, minimum levels are taken from the suggested quantities in the existing spare parts spreadsheets: an educated guess. After a few months, the consumption per machine (dashboard, machine pages) shows what is really used. Adjust the levels then. The dashboard's *Most used in the last 30 days* also points out fast-moving items that have no minimum yet.
