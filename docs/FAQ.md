# BPC Inventory: frequently asked questions

The 25 questions users ask most, with the shortest answer first and the clicks to get there. Menu paths are written as **Menu ▸ Item** → **Button**.

Roles in short: **Operators** read; **managers** record and order at their own site; **administrators** can do everything at every site, plus users and settings.

## Contents

**Access and account**
1. [I forgot my password, or I was logged out. What now?](#1-i-forgot-my-password-or-i-was-logged-out-what-now)
2. [Why can't I see the Issue button (or some menu items)?](#2-why-cant-i-see-the-issue-button-or-some-menu-items)
3. [Can I see the other site's stock? Can I change it?](#3-can-i-see-the-other-sites-stock-can-i-change-it)
4. [Why are the times different from my clock?](#4-why-are-the-times-different-from-my-clock)

**Finding stock**
5. [What do *Out*, *Below min* and *Refill* mean?](#5-what-do-out-below-min-and-refill-mean)
6. [What is a two-bin item?](#6-what-is-a-two-bin-item)
7. [What does criticality (High, Normal, Low) mean?](#7-what-does-criticality-high-normal-low-mean)

**Issuing and corrections**
8. [How do I record parts I took from the shelf?](#8-how-do-i-record-parts-i-took-from-the-shelf)
9. [I entered the wrong quantity. How do I undo it?](#9-i-entered-the-wrong-quantity-how-do-i-undo-it)
10. [Why can't I take out more than the system shows?](#10-why-cant-i-take-out-more-than-the-system-shows)

**Purchasing and receiving**
11. [What is the fastest way to order what is running out?](#11-what-is-the-fastest-way-to-order-what-is-running-out)
12. [How do I avoid ordering the same part twice?](#12-how-do-i-avoid-ordering-the-same-part-twice)
13. [Why is the price on an order line blank, or why does it ask for one?](#13-why-is-the-price-on-an-order-line-blank-or-why-does-it-ask-for-one)
14. [The goods arrived, but the supplier never confirmed or shipped the order. Can I receive them?](#14-the-goods-arrived-but-the-supplier-never-confirmed-or-shipped-the-order-can-i-receive-them)
15. [The supplier delivered less, or more, than ordered. What do I do?](#15-the-supplier-delivered-less-or-more-than-ordered-what-do-i-do)

**Transfers between sites**
16. [A part arrived from the other site. How do I record it?](#16-a-part-arrived-from-the-other-site-how-do-i-record-it)

**Counts and minimum levels**
17. [How do I set or change minimum levels?](#17-how-do-i-set-or-change-minimum-levels)
18. [How do stock counts work, and what if I did not count a line?](#18-how-do-stock-counts-work-and-what-if-i-did-not-count-a-line)
19. [How do I enter the stock we already have at go-live?](#19-how-do-i-enter-the-stock-we-already-have-at-go-live)

**Machines and catalogue**
20. [How do I add a new item or a new machine?](#20-how-do-i-add-a-new-item-or-a-new-machine)
21. [A machine was moved to the other site. What changes?](#21-a-machine-was-moved-to-the-other-site-what-changes)
22. [Why can't I delete an item, supplier or user?](#22-why-cant-i-delete-an-item-supplier-or-user)

**Dashboard, email and reports**
23. [Why didn't I get the daily email?](#23-why-didnt-i-get-the-daily-email)
24. [Why is the stock value different from the supplier's invoice?](#24-why-is-the-stock-value-different-from-the-suppliers-invoice)
25. [How do I get data into Excel, and how do I find out who changed something?](#25-how-do-i-get-data-into-excel-and-how-do-i-find-out-who-changed-something)

---

## Access and account

### 1. I forgot my password, or I was logged out. What now?

**Forgotten password:** on the login page click **Forgot your password?** and enter your email. You get a link to choose a new password.

- The email comes from *BPC Inventory <inventory@byldinc.com>*. It reaches **@byldinc.com addresses only**. If you have another address, or nothing arrives (check junk mail too), ask an administrator.
- An administrator can also send the link for you: **Admin ▸ Users** → open the user → **Email a password reset link**.

**Logged out without warning:** after a period without activity (60 minutes on the production server) you are logged out for security. Log in again; nothing you saved is lost. Anything typed into a form but not saved is lost, so save as you go.

**Login refused with *These credentials do not match our records.*** Either the email or password is wrong, or your account was deactivated. The message is the same on purpose, so it reveals nothing to a stranger. Ask an administrator.

More: [Getting started](user-guide/01-getting-started.md).

### 2. Why can't I see the Issue button (or some menu items)?

Every menu shows only what your role may use.

| Role | Sees |
|---|---|
| **Operator** | Dashboard, inventory, two-bin items, categories, purchase orders, machines. **No Issue button**, no stock counts, no min levels, no suppliers, no machine types, no admin. |
| **Manager** | Everything for their own site, including **Issue ▾** (Issue stock, Transfer in, Adjust stock). |
| **Administrator** | Everything, plus **Admin ▾** (Users, Sites, Reason codes, Audit log). |

Operators read only: they tell the site manager what they took, and the manager records it. If you need to record movements, ask an administrator to change your role.

A **403 · This action is unauthorized** page means you opened something your role or site does not allow, for example changing the other site's stock.

More: [Getting started](user-guide/01-getting-started.md), [User guide: Find your role](USER-GUIDE.md#find-your-role).

### 3. Can I see the other site's stock? Can I change it?

**Seeing it: yes.** **Stock ▸ Inventory** has one quantity column per site, and every item page shows *Stock by site*. Check this before ordering: the other site may have the part, and a transfer is usually faster than a 20–60 day order.

**Changing it: no**, unless you are an administrator. A manager records movements only at their own site. The one exception is a **transfer**: the receiving manager records it, and it reduces the sending site's stock in the same step (see [question 16](#16-a-part-arrived-from-the-other-site-how-do-i-record-it)).

Administrators switch sites with the blue **site label** in the header, including **All sites**.

More: [Inventory and items](user-guide/02-stock-and-items.md).

### 4. Why are the times different from my clock?

Times are shown in the **site's local time**: Georgia (BPC001) in New York time, Colorado (BPC002) in Denver time. The **All sites** view of administrators uses UTC.

So a manager in Colorado sees 08:00 for an issue entered at 10:00 in Georgia. Both are the same moment. The daily email follows the same rule: it goes out at the site's digest hour in local time.

More: [Getting started: Dates and numbers](user-guide/01-getting-started.md#dates-and-numbers).

---

## Finding stock

### 5. What do *Out*, *Below min* and *Refill* mean?

| Status | Meaning | Colour and icon |
|---|---|---|
| **Out** | A minimum is set and nothing is left | red, ✕ |
| **Below min** | Quantity is below the minimum level | orange ▲ for high criticality, amber ● otherwise |
| **Refill** | A two-bin item is at one bin or less | amber ● |

An item without a minimum level is never flagged. Quantities **on order** are shown next to the status but never clear it: the part is not on the shelf yet.

Where to see it: **Dashboard** (tiles at the top and *Stock to act on*), **Stock ▸ Inventory** with the **Needs replenishment** filter, and the daily email.

More: [Dashboard](user-guide/08-dashboard-and-digest.md), [Min levels](user-guide/04-min-levels-locations-two-bin.md#when-an-item-is-flagged).

### 6. What is a two-bin item?

A simple way to keep cheap, regularly used items (filters, grease, fasteners) in stock without watching numbers:

1. The item is kept in **two bins** on the shelf. Parts are taken from the first bin.
2. When the first bin is **empty**, reorder. The second bin covers the time until the delivery arrives.
3. The delivery refills the empty bin.

In the system, a two-bin item has a **Qty per bin** instead of a minimum level. It shows **Refill** as soon as one bin or less is left. Quick order suggests **one bin** for it.

Where: **Stock ▸ Two-bin items** lists them, refills first. Managers mark an item as two-bin in **Stock ▸ Min levels & locations** (tick **Two-bin**, enter **Qty per bin**).

The method is also known as *two-bin kanban*.

More: [Min levels, locations and two-bin items](user-guide/04-min-levels-locations-two-bin.md#two-bin-items).

### 7. What does criticality (High, Normal, Low) mean?

How badly a missing item hurts:

- **High**: its failure stops production and it is hard to get.
- **Normal**, **Low**: less critical.

Criticality decides:
- the order on the dashboard (high first);
- the warning colour (orange for high);
- how often the item should be counted: **High monthly**, **Normal and Low quarterly**.

It is set on the item: **Stock ▸ Inventory** → click the SKU → **Edit** (managers and administrators).

More: [Inventory and items](user-guide/02-stock-and-items.md#creating-and-editing-items).

---

## Issuing and corrections

### 8. How do I record parts I took from the shelf?

**Issue** (dark button in the header), managers and administrators. Three steps:

1. **Item**: type part of the SKU, name or manufacturer part number and pick from the list.
2. **Destination**: **Machine** (one of the machines at your site) or **General use** with a reason. *Other, see note* needs a note.
3. **Quantity**, in the item's unit, then **Issue**.

The form stays open with the same machine for the next part; **Done** returns to the inventory.

**Faster:** **Machines ▸ Machines** → open the machine (e.g. 004C) → **Issue** next to the part in its parts list. Machine and item are filled in.

**Why it matters:** every issue records quantity and cost per machine. That consumption is what will turn guessed minimum levels into measured ones.

More: [Issuing, transfers and adjustments](user-guide/05-issuing-transfers-adjustments.md#issuing-stock).

### 9. I entered the wrong quantity. How do I undo it?

Recorded movements cannot be edited or deleted. **Correct them with an adjustment** that does the opposite, with a note.

**Issue ▾ → Adjust stock** (or **Adjust** on the item page next to your site):

- **Direction**: **Increase** if you issued too much, **Decrease** if you issued too little or received too much.
- **Reason**: e.g. **Found, not recorded** or **Lost or missing**.
- **Note**: what you are correcting, e.g. *Issue of 9 Oct entered twice*. Then **Record adjustment**.

**Why:** the full history stays visible. Anyone can see what happened and when, and stock can always be rebuilt from the history. The database itself blocks changes to recorded movements.

More: [Adjusting stock](user-guide/05-issuing-transfers-adjustments.md#adjusting-stock).

### 10. Why can't I take out more than the system shows?

**Stock never goes negative.** If you try, the issue is refused with a message such as:

> *Only 2 pc available at BPC002.*

The shelf and the system disagree, so fix the record first:

1. Count the shelf.
2. If there really is more, record **Issue ▾ → Adjust stock**, **Increase**, reason **Found, not recorded**, with a note → **Record adjustment**.
3. Then issue.

Negative stock would hide the real error (a missed receipt, a wrong count) and make the stock value meaningless.

More: [Messages and what to do](user-guide/11-messages.md#stock-movements).

---

## Purchasing and receiving

### 11. What is the fastest way to order what is running out?

**Quick order from the dashboard** (managers and administrators):

1. **Dashboard**, under *Stock to act on*: tick the items → **Order selected**.
2. On **Quick order**, check each line:
   - **Supplier**: the one the item was last ordered from.
   - **Quantity**: one bin for a two-bin item; otherwise enough to reach twice the minimum, less what is already on order, rounded up to whole packs.
   - **Unit price**: blank means the supplier's last price.
3. **Create draft orders**: one draft per supplier (and per site).

Nothing is sent yet. Open each draft, check it, send it to the supplier yourself (email or portal), then click **Mark as sent to supplier**.

The classic way also works: **Purchasing ▸ Purchase orders** → **New order** → supplier and *Deliver to* → **Create draft** → add lines.

More: [Purchasing: Quick order](user-guide/06-purchasing.md#quick-order-from-the-dashboard).

### 12. How do I avoid ordering the same part twice?

Look at the **On order** column on the dashboard. It lists every order in progress for the item, **drafts included**: number, status and quantity still to come, e.g. `PO-2026-0007 · Draft · 10`.

On the **Quick order** screen, such items show *Already on PO-…* and start **unticked**. You have to tick them on purpose to order more.

The *Below min* status stays until the goods are received (see [question 5](#5-what-do-out-below-min-and-refill-mean)). Use the On order column, not the status, to see whether someone has already ordered.

More: [Purchasing: Avoiding double orders](user-guide/06-purchasing.md#quick-order-from-the-dashboard).

### 13. Why is the price on an order line blank, or why does it ask for one?

**Leave the price blank** and the system uses the **supplier's last price** for that item, shown in grey on Quick order. The last price is updated automatically each time an order is marked as sent.

If the supplier has **no last price** for the item, you must enter one. The message is:

> *Enter a price: Kraków warehouse has no last price for SP-10012.*

Enter the price from the supplier's quote. From then on it becomes the last price.

Supplier prices and pack sizes are kept in **Purchasing ▸ Suppliers** → supplier → *Items supplied*.

More: [Messages: Purchase orders](user-guide/11-messages.md#purchase-orders).

### 14. The goods arrived, but the supplier never confirmed or shipped the order. Can I receive them?

**Yes.** Receiving is possible as soon as the order is **Ordered** (marked as sent), even without a confirmation or a shipping notice.

**Purchasing ▸ Purchase orders** → open the order → **Receive goods** → check **Received now** per line → **Record receipt**.

Stock goes up at your site at the order price. The order becomes **Partially received** or **Received** by itself.

If the order is still a **Draft**, it was never marked as sent. Click **Mark as sent to supplier** first.

More: [Purchasing: Receiving goods](user-guide/06-purchasing.md#receiving-goods).

### 15. The supplier delivered less, or more, than ordered. What do I do?

**Less, rest still coming:** receive what arrived. The order stays **Partially received**; receive again when the rest comes.

**Less, rest will not come:** on the order, **Close short…** on that line, with a reason (e.g. *discontinued by the supplier*). No stock changes. If it was the last open line, the order becomes **Received**.

**More than ordered:** accepted, with a warning:

> *More than ordered was received for … This usually means a pack-size mix-up: check whether packs were counted instead of units.*

Quantities are always in the item's unit (pieces, litres…), never in packs. 1 box of 6 is 6 pc, not 6 boxes. If you entered packs by mistake, correct it with an adjustment ([question 9](#9-i-entered-the-wrong-quantity-how-do-i-undo-it)).

**Wrong order altogether:** **Cancel order…** with a reason. This is only possible while nothing has been received.

More: [Purchasing](user-guide/06-purchasing.md#when-the-rest-will-not-come).

---

## Transfers between sites

### 16. A part arrived from the other site. How do I record it?

The **receiving** manager records it, **when the goods are physically there**:

**Issue ▾ → Transfer in** → **From** (the sending site), item, quantity → **Record transfer**.

- Both sites change in one step: the sender goes down, you go up. There is no "in transit" stage, so do not record it when it is shipped.
- The goods arrive at the sender's average cost.
- If the sender has recorded less than what arrived, the transfer is refused:

  > *Only 1 pc available at BPC001. BPC001 must first correct its recorded stock with an adjustment; then enter the transfer again.*

  Call the sending site's manager. You cannot change their stock.

More: [Transfer in](user-guide/05-issuing-transfers-adjustments.md#transfer-in-from-the-other-site).

---

## Counts and minimum levels

### 17. How do I set or change minimum levels?

**Stock ▸ Min levels & locations** (managers for their own site, administrators for any site):

- **Min level**: below this the item is flagged. **Empty or 0 turns the alert off.**
- **Location**: the shelf, e.g. `CO-SP01`.
- **Two-bin** and **Qty per bin** for two-bin items.

**Save levels** saves the current page (50 rows). Use the filters (**No level set**, **Two-bin only**, category, search) to work through the catalogue.

Levels are **per site**: the same item can have a minimum of 2 in Colorado and none in Georgia.

**Where the numbers come from:** at go-live, the suggested quantities from the old spare-parts spreadsheets (an educated guess). After a few months, check consumption on the dashboard and machine pages, and adjust. *Most used in the last 30 days* on the dashboard flags fast movers with **No minimum set**; **Set a minimum** opens the editor for that item.

More: [Min levels, locations and two-bin items](user-guide/04-min-levels-locations-two-bin.md).

### 18. How do stock counts work, and what if I did not count a line?

**Stock ▸ Stock counts** → **New count**:

1. **Add by**: *Due for counting*, a category, a criticality, *Two-bin items* or a single item.
2. **Start counting**. The sheet is sorted by location; the expected quantity is hidden unless you tick **Show expected quantities**.
3. Enter what is on the shelf: **0** for an empty shelf, **leave the line empty if you did not count it**. **Save counts** as often as you like.
4. **Save and review**: compares your count with stock **now**, so issues made during the count are not lost.
5. **Post count**: one *Cycle count correction* per difference.

**Empty lines are skipped:** nothing is written for them, and they stay due for counting.

A posted count cannot be changed. **Cancel count** before posting discards it.

More: [Stock counts](user-guide/07-stock-counts.md#cycle-counts).

### 19. How do I enter the stock we already have at go-live?

As **opening balances**, one adjustment per item:

**Issue ▾ → Adjust stock** → reason **Opening balance**, direction **Increase** → item, quantity and **unit cost** (the last known price) → **Record adjustment**.

- Write where the price came from in the note, e.g. *price from Kraków list 2026*.
- The form keeps the reason and direction, ready for the next item.
- An opening balance also counts as the item's first count, so nothing shows as *due for counting* on day one.
- Set minimum levels and locations before or right after.

**Why a cost is needed:** stock value and the average cost start from it. Without it the values would be meaningless.

More: [Stock counts: The opening count](user-guide/07-stock-counts.md#the-opening-count-go-live), [Go-live checklist](user-guide/10-go-live.md).

---

## Machines and catalogue

### 20. How do I add a new item or a new machine?

**New item** (managers and administrators): **Stock ▸ Inventory** → **New item**.
- Required: SKU, name, category (a subcategory, not a top-level group), unit, criticality.
- The SKU is locked once the item has any stock movement.
- Then link it to a supplier: **Purchasing ▸ Suppliers** → supplier → *Items supplied*.

**New machine** (administrators only): **Machines ▸ Machines** → **Register machine**.
- Choose the type; the next free machine SKU is proposed, e.g. **007C** for a Truss Saw.
- The revision has the form `2.0`.
- The machine's parts come from its **machine type**'s parts list (**Machines ▸ Machine types**). Nothing has to be listed per machine.

More: [Inventory and items](user-guide/02-stock-and-items.md#creating-and-editing-items), [Machines and parts lists](user-guide/03-machines-and-parts-lists.md).

### 21. A machine was moved to the other site. What changes?

An administrator changes the machine's **Site**: **Machines ▸ Machines** → machine → **Edit**.

- From then on, stock is issued to it **at the new site** only.
- Its earlier consumption **stays recorded at the old site**, so history is not rewritten.
- Its SKU and type stay the same.

More: [Machines: relocating](user-guide/03-machines-and-parts-lists.md#registering-editing-and-relocating-machines-administrators).

### 22. Why can't I delete an item, supplier or user?

**Nothing is deleted. Records are deactivated instead.** Untick **Active** on the item, supplier, machine or user.

A deactivated record disappears from pickers and lists (tick **Include inactive** to see items again) but keeps its history. A deactivated user cannot log in, but the work they recorded stays attributed to them.

**Why:** every movement points to an item, a site, a person and often a machine or an order. Deleting any of them would break the history the stock figures are built from.

More: [Project: Rules the system enforces](PROJECT.md#7-rules-the-system-enforces).

---

## Dashboard, email and reports

### 23. Why didn't I get the daily email?

Check four things:

1. **Is it switched on?** It is off until you choose it: **Your name ▾ → Profile** → tick **Send me the daily low-stock digest** → save. Administrators can switch it on for anyone.
2. **Was there anything to report?** It is sent **only when something needs attention**: items below minimum, two-bin refills, orders past their ETA with nothing received, orders never confirmed. No email means nothing to report.
3. **Is it time yet?** It goes out once a day at the site's **digest hour** (default 07:00 local time; administrators change it under **Admin ▸ Sites**). Administrators get one email for all sites, by default at 07:00 New York time.
4. **Is your address @byldinc.com?** The production server sends through the company's Microsoft 365, which delivers only to @byldinc.com addresses. Also check junk mail; the sender is *inventory@byldinc.com*.

The dashboard always shows the same information, live.

More: [Daily digest](user-guide/08-dashboard-and-digest.md#daily-digest-by-email).

### 24. Why is the stock value different from the supplier's invoice?

Three reasons, all deliberate:

1. **Freight and duty are not included.** The value uses net supplier prices only, so it is lower than the true landed cost.
2. **Moving average cost.** Each item has one average cost **per site**:
   - Every receipt (at the order price) and every transfer in (at the sender's cost) blends into it.
   - Issues take stock out at the current average and do not change it.
   - So the value is a blend of past purchases, not the latest price.
3. **Each site has its own average.** The same item can be valued differently in Georgia and Colorado.

The value is fine for managing stock and spotting expensive items. It is not an asset valuation for accounting.

More: [Project: Rules the system enforces](PROJECT.md#7-rules-the-system-enforces).

### 25. How do I get data into Excel, and how do I find out who changed something?

**Excel:** every list has **Export CSV** (inventory, purchase orders, counts, movement histories, consumption per machine…).
- It exports what the list shows **with your current filters**, all pages at once.
- The file opens directly in Excel.

**Who changed it:**
- **Stock movements** (issues, receipts, transfers, adjustments, counts) are in the item's **Movement history**, with who and when, for everyone: **Stock ▸ Inventory** → click the SKU.
- **Changes to master data** (items, suppliers, machines, parts lists, minimum levels, users, sites…) are in **Admin ▸ Audit log**, administrators only. It shows who, when, and each field before → after. Filter by record, person and dates.

Ask an administrator if you need something from the audit log. Passwords are never recorded.

More: [Getting started: Exporting](user-guide/01-getting-started.md#exporting-to-excel), [Administration: Audit log](user-guide/09-administration.md#audit-log).
