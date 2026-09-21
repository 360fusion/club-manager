import { usePage } from '@inertiajs/vue3';

// The currency of the club whose page is open (shared by the server on every request).
export function currency() {
    return usePage().props.currency ?? { code: 'GBP', symbol: '£', name: 'Pound sterling' };
}

export function currencyCode() {
    return currency().code;
}

export function currencySymbol() {
    return currency().symbol;
}

export function formatMoney(amount, { decimals = 2 } = {}) {
    const value = Number(amount || 0);
    const sign = value < 0 ? '-' : '';

    return sign + currencySymbol() + Math.abs(value).toLocaleString('en-GB', { minimumFractionDigits: decimals, maximumFractionDigits: decimals });
}
