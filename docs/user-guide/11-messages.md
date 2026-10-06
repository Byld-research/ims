# 11. Messages and what to do

[← Back to README](../../README.md)

The system refuses anything that would make the records wrong, and says why. The most common messages, in the words the screen uses:

## Stock movements

| Message | Why | What to do |
|---|---|---|
| *Only 2 pc available at BPC002.* | You are taking out more than the system has recorded at this site. | Count the shelf. If there really are more, record an adjustment (*Found, not recorded*) first, then issue. |
| *Enter a unit cost: this item has no average cost yet at BPC002.* | Stock is being added for an item that has never had a cost at this site. | Enter the unit cost: last known price, with its source in the note. |
| *Only 1 pc available at BPC001. BPC001 must first correct its recorded stock with an adjustment; then enter the transfer again.* | The sending site has recorded less than what arrived. | Ask the sending site's manager to correct their stock, then enter the transfer again. |
| *That machine is not active at this site.* | The machine is at the other site, or deactivated. | Check the machine's site on its page; issue at that site. |
| *Machine 004C is inactive; stock cannot be issued to it.* | The machine was deactivated. | Ask an administrator, or issue as *General use*. |
| *Say what the stock was used for.* | General issue with reason *Other, see note* but no note. | Add a short note. |
| *Pick an item from the list.* | The item was typed but not chosen from the suggestions. | Type part of the SKU or name and click a suggestion. |

## Purchase orders

| Message | Why | What to do |
|---|---|---|
| *Lines can be changed only while the order is a draft.* | The order was sent; the supplier works from it. | For more items, create another order. To drop a line, close it short. |
| *Add at least one line before sending the order.* | Empty draft. | Add lines first. |
| *Enter a price: Kraków warehouse has no last price for SP-10012.* | Nobody has ordered this item from this supplier yet. | Enter the price from the quote. |
| *An order that is draft cannot become confirmed.* | Steps must follow in order. | Use the button the order page shows. |
| *Goods have been received on this order, so it cannot be cancelled. Close the remaining lines short instead.* | Something already arrived. | **Close short…** on the open lines. |
| *Line SP-10001 was closed short and cannot receive more.* | The line was closed earlier. | Receive the extra quantity with an adjustment (*Found, not recorded*) and a note with the order number, or order it again. |
| *More than ordered was received for …* (yellow) | Saved, but suspicious. | Check whether packs were entered as units. If wrong, correct with an adjustment. |

## Counts

| Message | Why | What to do |
|---|---|---|
| *Add at least one item before counting.* | The count is empty. | Add items, then start counting. |
| *Lines can be added only while the count is a draft.* | Counting already started. | Create a second stock count for the extra items. |
| *Only a count in progress can be posted. This one is posted.* | Someone already posted it. | Nothing to do; open the count to see the result. |
| *SP-10005: Enter a unit cost: …* | A found item has no cost at this site yet. | Enter its unit cost on the review screen. |

## Catalogue and machines

| Message | Why | What to do |
|---|---|---|
| *The SKU cannot be changed because the item already has stock movements.* | Changing it would break the history. | Keep the SKU; create a new item if the number really must change, and deactivate the old one. |
| *Choose a category that items can be assigned to, not a top-level group.* | Top-level groups only group. | Choose a subcategory. |
| *The SKU does not match the agreed numbering pattern.* | The number does not follow the agreed scheme. | Use the scheme shown under the field. |
| *A Truss Saw machine’s SKU must end in C, e.g. 007C.* | Machine SKU letter and type disagree. | Use the proposed SKU. |
| *This item is already listed for all revisions. Limit that line to a revision first.* | A parts list line cannot be both general and revision-specific. | Edit the existing line's revision, then add the other revision. |
| *Nothing was imported. Fix the lines below and upload the file again.* | At least one line of the CSV is wrong. | Fix the listed lines; the import is all or nothing. |

## Login and access

| Message | Why | What to do |
|---|---|---|
| *These credentials do not match our records.* | Wrong email or password, or the account is deactivated. | Use **Forgot your password?**, or ask an administrator. |
| *This account has been deactivated.* | Deactivated while logged in. | Ask an administrator. |
| **403 · This action is unauthorized** | Your role or site does not allow it, e.g. changing the other site's stock. | Ask the other site's manager, or an administrator. |

Something wrong in the records that no message explains? Tell an administrator. Every movement is recorded with who and when, and nothing can be silently changed, so it can always be traced.
