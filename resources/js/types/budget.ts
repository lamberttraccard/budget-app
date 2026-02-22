export type CategoryTotal = {
    id: number;
    name: string;
    slug: string;
    color: string;
    icon: string | null;
    amountCents: number;
    transactionCount: number;
    trendPercentage: number | null;
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

export type CategoryOption = {
    id: number;
    name: string;
    color: string;
    icon: string | null;
};

export type SubcategoryTotal = {
    id: number;
    name: string;
    amountCents: number;
    transactionCount: number;
};

export type ImportRecord = {
    id: number;
    fileName: string;
    bank: string;
    transactionCount: number;
    dateFrom: string | null;
    dateTo: string | null;
    importedAt: string | null;
};

export type AccountContext = {
    value: string;
    label: string;
};

export type TrendData = {
    hasPreviousMonthData: boolean;
    categoryTrends: Record<string, number | null>;
};

export type CategoryShowProps = {
    category: {
        id: number;
        name: string;
        color: string;
        slug: string;
        icon: string | null;
    };
    subcategories: SubcategoryTotal[];
    transactions?: TransactionItem[];
    totalAmountCents: number;
    transactionCount: number;
    currentMonth: string;
    currentAccount: string;
};

export type SearchResultItem = {
    id: number;
    date: string;
    description: string;
    amountCents: number;
    categoryId: number;
    categoryName: string;
    categoryColor: string;
    accountName: string;
};

export type SearchProps = {
    results: SearchResultItem[];
    totalCount: number;
    query: string;
    currentAccount: string | null;
};

export type RecurringTransaction = {
    id: number;
    date: string;
    amountCents: number;
    accountName: string;
};

export type RecurringMerchant = {
    merchantName: string;
    categoryId: number | null;
    categoryName: string;
    categoryColor: string;
    typicalAmountCents: number;
    frequency: string;
    lastSeenMonth: string;
    monthCount: number;
    amountVaries: boolean;
    minAmountCents: number;
    maxAmountCents: number;
    transactions: RecurringTransaction[];
};

export type RecurringProps = {
    recurringMerchants?: RecurringMerchant[];
    currentAccount: string | null;
};

export type DashboardProps = {
    categoryTotals: CategoryTotal[];
    incomeCents: number;
    expensesCents: number;
    currentMonth: string;
    currentAccount: string;
    availableMonths: string[];
    importPreview?: ImportPreview | null;
    trendData?: TrendData;
    imports?: ImportRecord[];
};

export type SharedProps = {
    allCategories: CategoryOption[];
};
