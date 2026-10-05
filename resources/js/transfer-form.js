import { formatQty, lookupStock, selectedItemId } from './stock-lookup';

/**
 * Transfer in: shows stock at both sites before and after (SPEC 7, principle 4).
 */
export default ({ lookupUrl, fromId = '', toId = '', qty = '' }) => ({
    fromId: fromId ? String(fromId) : '',
    toId: toId ? String(toId) : '',
    qty,
    from: null,
    to: null,

    init() {
        const fixedTo = this.$root.querySelector('input[type="hidden"][name="to_site_id"]');
        if (fixedTo) this.toId = fixedTo.value;
        this.$nextTick(() => this.lookup());
    },

    async lookup() {
        const itemId = selectedItemId(this.$root);
        [this.from, this.to] = await Promise.all([
            lookupStock(lookupUrl, itemId, this.fromId),
            lookupStock(lookupUrl, itemId, this.toId),
        ]);
    },

    change() {
        return parseFloat(this.qty) || 0;
    },

    format: formatQty,
});
