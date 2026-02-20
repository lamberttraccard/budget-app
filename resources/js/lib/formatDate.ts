const formatter = new Intl.DateTimeFormat('en-US', {
    month: 'short',
    day: 'numeric',
});

export function formatDate(isoDate: string): string {
    return formatter.format(new Date(isoDate + 'T00:00:00'));
}
