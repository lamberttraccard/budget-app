export type CategoryTotal = {
    id: number;
    name: string;
    slug: string;
    color: string;
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
    allCategories: CategoryOption[];
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
    allCategories: CategoryOption[];
};

export type DashboardProps = {
    categoryTotals: CategoryTotal[];
    incomeCents: number;
    expensesCents: number;
    currentMonth: string;
    currentAccount: string;
    availableMonths: string[];
    hasPreviousMonthData: boolean;
    importPreview?: ImportPreview | null;
    imports: ImportRecord[];
};
