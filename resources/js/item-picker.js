/**
 * Type-ahead item search used wherever a form needs one catalogue item.
 */
export default (searchUrl, initial = null) => ({
    query: initial ? initial.label : '',
    selected: initial,
    results: [],
    active: 0,
    open: false,
    loading: false,
    searched: false,

    async search() {
        this.selected = null;

        if (this.query.trim().length < 2) {
            this.results = [];
            this.open = false;
            this.searched = false;
            return;
        }

        this.loading = true;
        const response = await fetch(`${searchUrl}?q=${encodeURIComponent(this.query.trim())}`, {
            headers: { Accept: 'application/json' },
        });
        this.results = response.ok ? await response.json() : [];
        this.loading = false;
        this.searched = true;
        this.active = 0;
        this.open = this.results.length > 0;
    },

    move(step) {
        if (!this.results.length) return;
        this.active = (this.active + step + this.results.length) % this.results.length;
    },

    choose(item) {
        this.selected = item;
        this.query = `${item.sku} · ${item.name}`;
        this.open = false;
        this.$dispatch('item-selected', item);
    },
});
