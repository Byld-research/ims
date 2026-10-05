/**
 * Adjustment form: looks up current stock and shows the resulting quantity before saving.
 * Display only; the server recalculates everything under a row lock.
 */
export default (lookupUrl, direction = 'in', siteId = '', qty = '') => ({
    lookupUrl,
    direction,
    siteId: siteId ? String(siteId) : '',
    itemId: null,
    qty,
    stock: null,

    init() {
        const hidden = this.$root.querySelector('input[name="item_id"]');
        if (hidden && hidden.value) {
            this.itemId = hidden.value;
        }
        const fixedSite = this.$root.querySelector('input[type="hidden"][name="site_id"]');
        if (fixedSite) {
            this.siteId = fixedSite.value;
        }
        this.$nextTick(() => this.lookup());
    },

    async lookup() {
        const hidden = this.$root.querySelector('input[name="item_id"]');
        this.itemId = hidden?.value || this.itemId;
        if (!this.itemId || !this.siteId) {
            this.stock = null;
            return;
        }
        const response = await fetch(`${this.lookupUrl}?item=${this.itemId}&site=${this.siteId}`, {
            headers: { Accept: 'application/json' },
        });
        this.stock = response.ok ? await response.json() : null;
    },

    after() {
        if (!this.stock) return 0;
        const current = parseFloat(this.stock.qty) || 0;
        const change = parseFloat(this.qty) || 0;
        return this.direction === 'in' ? current + change : current - change;
    },

    format(value) {
        const number = Number(value ?? 0);
        return Number.isFinite(number) ? number.toLocaleString('en-US', { maximumFractionDigits: 3 }) : '';
    },
});
