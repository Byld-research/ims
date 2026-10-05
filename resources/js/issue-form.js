import { formatQty, lookupStock, selectedItemId } from './stock-lookup';

/**
 * Issue form: item → machine (or general reason) → quantity (SPEC 7, principle 1).
 */
export default ({ lookupUrl, siteId = '', machines = [], machineId = '', mode = 'machine', qty = '' }) => ({
    siteId: siteId ? String(siteId) : '',
    machines,
    machineId: machineId ? String(machineId) : '',
    mode,
    qty,
    stock: null,

    init() {
        const fixedSite = this.$root.querySelector('input[type="hidden"][name="site_id"]');
        if (fixedSite) this.siteId = fixedSite.value;
        this.$nextTick(() => this.lookup());
    },

    siteMachines() {
        return this.machines.filter((m) => String(m.site_id) === this.siteId);
    },

    siteChanged() {
        if (!this.siteMachines().some((m) => String(m.id) === this.machineId)) this.machineId = '';
        this.lookup();
    },

    /** @param {number|null} itemId the item just picked; otherwise read from the form */
    async lookup(itemId = null) {
        this.stock = await lookupStock(lookupUrl, itemId ?? selectedItemId(this.$root), this.siteId);
    },

    after() {
        return this.stock ? (parseFloat(this.stock.qty) || 0) - (parseFloat(this.qty) || 0) : 0;
    },

    format: formatQty,
});
