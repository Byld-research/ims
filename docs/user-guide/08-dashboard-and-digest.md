# 8. Dashboard and daily digest

[← Back to README](../../README.md)

## Dashboard

The first page after login, for your site. Administrators can switch to **All sites**, where every row shows its site.

![Dashboard for Colorado](images/dashboard.png)

The dashboard is ordered by urgency: what needs action first, what is moving next, the overall figures last.

### 1. Status bar

Five tiles across the top. A tile with something to do is coloured, with an icon and the count; a tile with nothing to do turns grey and reads **All clear**. Click a tile to jump to its list.

| Tile | Icon | Counts |
|---|---|---|
| **Out of stock** | ✕ red | items with a minimum and nothing left |
| **Below minimum** | ▲ orange | items under their minimum |
| **Two-bin refill** | ● amber | two-bin items at one bin or less |
| **Orders to chase** | ▲ orange | orders past their ETA, unconfirmed, or partly received for over 30 days |
| **Due for counting** | ● amber | items due by the suggested count frequency |

Colour is never the only signal: every state also has its own icon shape and a word.

### 2. Stock to act on

Every item that needs replenishment, **worst first**: out of stock, then high criticality, then the item with the least left compared to its minimum. Each row shows:

- a coloured stripe and a status: **Out** (red ✕), **Below min** (orange ▲ for high criticality, amber ● otherwise), **Refill** (amber ●); the criticality is written under the item name;
- a **level bar**: the fill is the stock, the black mark is the minimum (or one bin of a two-bin item);
- what is already **on order**;
- **used, 12 weeks**: a small line of weekly usage, the current week as a blue dot. A flat line or *not used* means the item is not moving; a busy line means it needs watching.

### 3. Orders to chase

Late, unconfirmed and half-delivered orders, with the reason and the date that matters. Click one to open it.

### 4. Most used in the last 30 days

The items that are actually moving, by value used, with their stock level against the minimum. **Keep these above their minimum.** A fast mover marked **No minimum set** is not being watched; **Set a minimum** opens the level editor for that item. Healthy levels are shown in grey, so the colour stays on what needs attention.

### 5. Overview

Quieter figures below the work:

- **Stock value**: quantity × average cost at the site, net supplier prices, freight and duty excluded.
- **Consumption this month so far**, with last month's total.
- **Below minimum** out of the items that have a minimum.
- **No movement in 12 months**: stock that may not need to be held.
- **Stock value by category** and **top machines by consumption** over 90 days. Hover a bar for details; click a machine to open it.

Usage is shown, not a forecast: the dashboard does not estimate when an item will run out.

Operators see the same dashboard for their site, without action links:

![Operator dashboard in Georgia](images/operator-dashboard.png)

## Daily digest by email

One email a day with what needs attention: items below minimum, two-bin refills, orders past their ETA, orders never confirmed.

- Sent at the site's **digest hour** (default 07:00 local time), set by an administrator per site.
- **Only when something needs attention.** No email means nothing to report.
- Only to users who switched it on: **Your name ▾ → Profile → Send me the daily low-stock digest**. Administrators can switch it for anyone.
- Administrators get **one email for all sites**, by default at 07:00 New York time.
