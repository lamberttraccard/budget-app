export type CategoryTotal = {
    id: number;
    name: string;
    slug: string;
    color: string;
    amountCents: number;
    transactionCount: number;
};

export type ImportPreview = {
    bank: string;
    transactionCount: number;
    dateFrom: string;
    dateTo: string;
    fileName: string;
};

export type DashboardProps = {
    categoryTotals: CategoryTotal[];
    incomeCents: number;
    expensesCents: number;
    currentMonth: string;
    importPreview?: ImportPreview | null;
};
