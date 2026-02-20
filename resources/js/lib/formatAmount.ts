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
