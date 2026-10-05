/**
 * Shared by the movement forms: fetch current stock for an item at a site, and format quantities.
 * Display only; the server recalculates everything under a row lock.
 */
export async function lookupStock(url, itemId, siteId) {
    if (!itemId || !siteId) return null;
    const response = await fetch(`${url}?item=${itemId}&site=${siteId}`, { headers: { Accept: 'application/json' } });
    return response.ok ? response.json() : null;
}

export function formatQty(value) {
    const number = Number(value ?? 0);
    return Number.isFinite(number) ? number.toLocaleString('en-US', { maximumFractionDigits: 3 }) : '';
}

export function selectedItemId(root) {
    return root.querySelector('input[name="item_id"]')?.value || null;
}
