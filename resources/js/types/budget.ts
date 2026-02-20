export type CategoryTotal = {
    id: number;
    name: string;
    slug: string;
    color: string;
    amountCents: number;
    transactionCount: number;
};

export type DashboardProps = {
    categoryTotals: CategoryTotal[];
    incomeCents: number;
    expensesCents: number;
    currentMonth: string;
};
