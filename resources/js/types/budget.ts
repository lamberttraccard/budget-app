export type CategoryTotal = {
    id: number;
    name: string;
    slug: string;
    color: string;
    icon: string | null;
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
    status: 'completed' | 'categorizing' | 'categorized';
    uncategorizedCount: number;
    batchId: string | null;
};

export type AccountContext = {
    id: number;
    name: string;
    bank: string;
};

export type AverageData = {
    avgIncomeCents: number;
    avgExpensesCents: number;
    categoryAverages: Record<string, { avgAmountCents: number }>;
    completeMonthCount: number;
};

export type MonthlyTotal = {
    month: string;
    amountCents: number;
};

export type SparklineData = {
    monthlyIncomeTotals: MonthlyTotal[];
    monthlyExpenseTotals: MonthlyTotal[];
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
    selectedAccountIds: number[];
    monthlyTotals: MonthlyTotal[];
    chartMonth: string;
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
    selectedAccountIds: number[];
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
    isActive: boolean;
    minAmountCents: number;
    maxAmountCents: number;
    transactions: RecurringTransaction[];
};

export type RecurringProps = {
    recurringMerchants?: RecurringMerchant[];
    selectedAccountIds: number[];
};

export type DashboardProps = {
    categoryTotals: CategoryTotal[];
    incomeCents: number;
    expensesCents: number;
    currentMonth: string;
    selectedAccountIds: number[];
    availableMonths: string[];
    importPreview?: ImportPreview | null;
    averageData?: AverageData;
    sparklineData?: SparklineData;
    imports?: ImportRecord[];
};

export type SharedProps = {
    allCategories: CategoryOption[];
    selectedAccountIds: number[];
    accountContexts: AccountContext[];
    flash?: {
        updated_count?: number;
    };
};

export type MerchantRuleItem = {
    id: number;
    merchantPattern: string;
    categoryId: number;
    categoryName: string;
    categoryColor: string;
    categoryIcon: string | null;
    matchCount: number;
};

export type MerchantRulesProps = {
    rules: MerchantRuleItem[];
};
