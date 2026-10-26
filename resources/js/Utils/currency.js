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

/**
 * "£1,234.50" (or "-£5.00") in the club's currency. Takes a number or a numeric string, including one the server already
 * formatted with thousands commas ("1,234.50"). Also in templates as `$money(amount)`.
 */
export function formatMoney(amount, { decimals = 2 } = {}) {
    const value = Number(String(amount ?? 0).replace(/,/g, '')) || 0;
    const sign = value < 0 ? '-' : '';

    return sign + currencySymbol() + Math.abs(value).toLocaleString('en-GB', { minimumFractionDigits: decimals, maximumFractionDigits: decimals });
}
