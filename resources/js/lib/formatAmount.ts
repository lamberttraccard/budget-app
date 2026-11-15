const formatter = new Intl.NumberFormat('fr-FR', {
    style: 'currency',
    currency: 'EUR',
    minimumFractionDigits: 2,
    maximumFractionDigits: 2,
});

export function formatAmount(cents: number): string {
    return formatter.format(cents / 100);
}

export function formatAbsAmount(cents: number): string {
    return formatter.format(Math.abs(cents) / 100);
}

/** Splits a formatted amount before its decimal comma: "1 005,60 €" → ["1 005", ",60 €"]. */
export function splitDecimals(formatted: string): [string, string] {
    const index = formatted.lastIndexOf(',');
    return index === -1 ? [formatted, ''] : [formatted.slice(0, index), formatted.slice(index)];
}
