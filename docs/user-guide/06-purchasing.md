# 6. Purchasing

[← Back to the user guide](../USER-GUIDE.md)

Purchase orders are raised by the **manager of the site that needs the goods**; each order delivers to one site. Everyone can read orders. Payment and invoices are handled outside the system.

**Before ordering**, check the other site's column in the inventory: a transfer is usually faster than an order with a 20–60 day lead time.

## The order list

**Purchasing → Purchase orders**: orders for your site, newest first. Filter by status (*Open* = not closed or cancelled), supplier, or order number / tracking reference. Orders past their ETA show **Late**.

![Order list](images/po-list.png)

## Step by step

| Step | In the system | Status after |
|---|---|---|
| 1. Create the order | **New order** → supplier, *Deliver to* → **Create draft**. The number is assigned now, e.g. PO-2026-0005. | Draft |
| 2. Add lines | Item, quantity (in the item's unit), unit price. **Leave the price empty** to use the supplier's last price. Lines can be edited or removed only in the draft. | Draft |
| 3. Send it | Send the order to the supplier yourself (email, portal), then **Mark as sent to supplier**. The prices become the supplier's last prices. | Ordered |
| 4. Confirmation | When the supplier confirms: **Record confirmation** with the delivery date (ETA). | Confirmed |
| 5. Shipment | When it ships: **Record shipment** with the tracking number or link. A link becomes clickable on the order. | Shipped |
| 6. Goods arrive | **Receive goods** (see below), as often as deliveries come | Partially received / Received |
| 7. Paid | When payment is settled: **Close order** | Closed |

The order page always shows only the next possible step.

![An order past its ETA](images/po-late.png)

Goods sometimes arrive without a confirmation or shipping notice: receiving is possible from *Ordered* on.

Dates set by the steps (ordered, confirmed, ETA, shipped, closed) and the tracking reference can be corrected under **Details → Edit**.

## Quick order from the dashboard

The fastest way to reorder what is short. On the **Dashboard**, under *Stock to act on*:

1. Tick the items to order, then **Order selected**.

   ![Ticking items on the dashboard](images/dashboard-quick-order.png)

2. On **Quick order**, check each line:
   - **Supplier**: the one the item was last ordered from. Without one, choose it; any active supplier can be chosen.
   - **Quantity**: suggested, change it if needed. A two-bin item gets **one bin**. Any other item gets enough to reach **twice its minimum**, less what is already on order, rounded up to whole packs of that supplier.
   - **Unit price**: leave it blank to use the supplier's last price (shown in grey). A supplier with no last price needs a price.
3. **Create draft orders**. One draft is created per supplier (and per site), e.g. three suppliers give three drafts. Nothing is sent yet: continue from step 3 above (**Mark as sent to supplier**) on each draft.

![Quick order](images/quick-order.png)

**Avoiding double orders.** Items that are already on an order, **drafts included**, show it: the order number and status appear in the *On order* column of the dashboard and as *Already on PO-…* on the quick order screen. Such items start unticked; tick them only if you really want to order more.

## Receiving goods

**Receive goods** on the order page.

![Receiving against an order](images/po-receive.png)

- One row per line still open; **Received now** defaults to what is outstanding. Change it to what actually arrived; leave a line empty or 0 if nothing came for it.
- **Record receipt** adds the stock at your site at the order price.
- The order becomes **Partially received** or, when everything is in, **Received** automatically.
- **More than ordered** is accepted, with a warning. It usually means packs were counted instead of units (e.g. 1 box of 6 entered as 6 boxes). Check before saving.

## When the rest will not come

**Close short…** on the line (from *Ordered* on), with a reason such as *discontinued by the supplier*. The reason is written into the order notes; no stock changes. If that was the last open line, the order becomes **Received**.

## Cancelling

**Cancel order…** with a reason, possible only while **nothing has been received**. After a first receipt, close the remaining lines short instead.

## What to watch

The dashboard and the daily digest list orders **past their ETA with nothing received**, orders **sent but never confirmed**, and orders **partly received for over 30 days**. Chase those suppliers; a customs hold is the usual reason for a late international shipment.
