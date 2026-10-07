# 7. Stock counts

[← Back to the user guide](../USER-GUIDE.md)

## The opening count (go-live)

Before go-live, every item on the shelves is counted once and entered as an **adjustment with reason Opening balance**:

1. **Issue ▾ → Adjust stock**, reason **Opening balance**, direction **Increase**.
2. For each item: pick it, enter the quantity and the **unit cost**. Use the last known price (supplier price list, last invoice) and say where it came from in the note, e.g. *price from Kraków list 2026*.
3. Save. The form stays open with the same reason, ready for the next item.

An opening balance also counts as the item's first physical count, so items do not show as *due for counting* on day one.

Set minimum levels and locations (chapter 4) before or right after the opening count.

## Cycle counts

Counting a portion of the stock regularly keeps the records right (cycle counting). **Stock → Stock counts** (managers and administrators).

**Suggested frequency**: **high** criticality **monthly**; **normal**, **low** and **two-bin** items **quarterly**. The dashboard and the count list show how many items are due.

### 1. Create the count

**New count** → scope note, e.g. *High criticality monthly*. The reference is assigned: SC-2026-0003.

### 2. Add items

**Add by**: *Due for counting* (the suggested set), a category, a criticality, *Two-bin items*, or a single item. Repeat to combine. Items can be added or removed only before counting starts.

### 3. Count

**Start counting**. The count sheet is sorted **by location**, so you walk the shelves in order. The expected quantity is hidden unless you tick **Show expected quantities**: count what is there, don't confirm what the system says.

![Count sheet sorted by location](images/count-sheet.png)

- Enter **0** for an empty shelf; leave a line **empty** if you did not count it.
- **Save counts** as often as you like; **Export CSV** prints a sheet to take to the shelves.

### 4. Review and post

**Save and review**:

![Review before posting](images/count-review.png)

- For each line: quantity when added, **stock now**, counted, and the adjustment that will be written, with its value.
- **Stock that moved during counting** (an issue or receipt after the item was added) is highlighted. The adjustment is taken against stock **now**, so those movements are not lost. Check: was the shelf counted before or after the movement?
- A found item that has no cost at this site yet asks for a unit cost on that line.
- **Post count** writes one *Cycle count correction* adjustment per difference and marks every counted item as counted today. Uncounted lines are skipped.

A posted count cannot be changed or posted again. Mistakes are corrected with a new adjustment. **Cancel count** before posting discards it without changing stock.
