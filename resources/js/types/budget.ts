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

export type TransactionItem = {
    id: number;
    date: string;
    description: string;
    amountCents: number;
    categoryId: number;
};

export type SubcategoryTotal = {
    id: number;
    name: string;
    amountCents: number;
    transactionCount: number;
};

export type AccountContext = {
    value: string;
    label: string;
};

export type CategoryShowProps = {
    category: {
        id: number;
        name: string;
        color: string;
        slug: string;
    };
    subcategories: SubcategoryTotal[];
    transactions: TransactionItem[];
    totalAmountCents: number;
    transactionCount: number;
    currentMonth: string;
    currentAccount: string;
};

export type DashboardProps = {
    categoryTotals: CategoryTotal[];
    incomeCents: number;
    expensesCents: number;
    currentMonth: string;
    currentAccount: string;
    availableMonths: string[];
    importPreview?: ImportPreview | null;
};
