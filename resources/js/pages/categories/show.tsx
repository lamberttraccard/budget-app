import { Deferred, Head, router, usePage } from '@inertiajs/react';
import { motion, useReducedMotion } from 'motion/react';
import { useCallback, useRef, useState } from 'react';
import { toast } from 'sonner';
import ApplyRuleConfirmDialog from '@/components/ApplyRuleConfirmDialog';
import CategoryMonthlyChart from '@/components/CategoryMonthlyChart';
import CategoryPicker from '@/components/CategoryPicker';
import ColorHeader from '@/components/ColorHeader';
import SubcategoryRow from '@/components/SubcategoryRow';
import TransactionRow from '@/components/TransactionRow';
import type { TransactionTapData } from '@/components/TransactionRow';
import { Skeleton } from '@/components/ui/skeleton';
import AppLayout from '@/layouts/app-layout';
import type { CategoryShowProps, SharedProps, TransactionItem } from '@/types';


function TransactionListSkeleton() {
    return (
        <div className="rounded-xl overflow-hidden bg-surface/80 backdrop-blur-2xl">
            {Array.from({ length: 6 }).map((_, i) => (
                <div key={i}>
                    {i > 0 && <div className="mx-4 border-t border-white/5" />}
                    <div className="flex items-center gap-3 px-4 py-3">
                        <Skeleton className="h-3 w-16 shrink-0" />
                        <Skeleton className="h-3 flex-1" />
                        <Skeleton className="h-3 w-20 shrink-0" />
                    </div>
                </div>
            ))}
        </div>
    );
}

export default function CategoryShow({
    category,
    subcategories,
    transactions = [],
    totalAmountCents,
    transactionCount,
    currentMonth,
    selectedAccountIds,
    monthlyTotals,
    chartMonth,
}: CategoryShowProps) {
    const { allCategories } = usePage<SharedProps>().props;
    const [activeSubcategoryId, setActiveSubcategoryId] = useState<number | null>(null);
    const [selectedTransactionId, setSelectedTransactionId] = useState<number | null>(null);
    const [selectedTransactionCategoryId, setSelectedTransactionCategoryId] = useState<number | null>(null);
    const [selectedDescription, setSelectedDescription] = useState<string | null>(null);
    const [pendingCategoryId, setPendingCategoryId] = useState<number | null>(null);
    const [matchCount, setMatchCount] = useState<number | null>(null);
    const [isPatching, setIsPatching] = useState(false);
    const pickerTriggerRef = useRef<HTMLSpanElement>(null);
    const selectedTransactionIdRef = useRef<number | null>(null);

    const handleAccountsChange = useCallback((accountIds: number[]) => {
        const params: Record<string, string> = { month: currentMonth };
        if (chartMonth) params.chart_month = chartMonth;
        if (accountIds.length > 0) params.accounts = accountIds.join(',');
        router.get(window.location.pathname, params, {
            preserveState: true,
            preserveScroll: true,
        });
    }, [currentMonth, chartMonth]);

    const handleSubcategoryClick = (subcategoryId: number) => {
        setActiveSubcategoryId(
            activeSubcategoryId === subcategoryId ? null : subcategoryId,
        );
    };

    const handleTransactionTap = useCallback((data: TransactionTapData) => {
        if (isPatching) return;
        setSelectedTransactionId(data.id);
        selectedTransactionIdRef.current = data.id;
        setSelectedTransactionCategoryId(data.categoryId);
        setSelectedDescription(data.description);

        if (pickerTriggerRef.current) {
            const rect = data.element.getBoundingClientRect();
            Object.assign(pickerTriggerRef.current.style, {
                top: `${rect.bottom}px`,
                left: `${rect.left}px`,
                width: `${rect.width}px`,
            });
        }
    }, [isPatching]);

    const clearState = useCallback(() => {
        setSelectedTransactionId(null);
        selectedTransactionIdRef.current = null;
        setSelectedTransactionCategoryId(null);
        setSelectedDescription(null);
        setPendingCategoryId(null);
        setMatchCount(null);
    }, []);

    const patchTransaction = useCallback((applyToAll: boolean) => {
        if (selectedTransactionId === null || pendingCategoryId === null) return;
        setIsPatching(true);

        const data = applyToAll
            ? { category_id: pendingCategoryId, apply_to_all: true as const }
            : { category_id: pendingCategoryId };

        router.patch(`/transactions/${selectedTransactionId}`, data, {
            preserveScroll: true,
            onSuccess: (page) => {
                clearState();
                if (applyToAll) {
                    const props = page.props as unknown as { flash?: { updated_count?: number } };
                    const count = props.flash?.updated_count;
                    toast(`Updated ${count ?? 0} transactions`);
                } else {
                    toast("Got it, I'll remember");
                }
            },
            onFinish: () => setIsPatching(false),
        });
    }, [selectedTransactionId, pendingCategoryId, clearState]);

    const handleCategorySelect = useCallback((categoryId: number) => {
        if (selectedTransactionId === null || isPatching) return;

        // F6: Skip if same category
        if (categoryId === selectedTransactionCategoryId) {
            clearState();
            return;
        }

        setPendingCategoryId(categoryId);

        // Fetch match count
        fetch(`/transactions/match-count?description=${encodeURIComponent(selectedDescription ?? '')}`)
            .then((response) => {
                if (!response.ok) {
                    toast.error('Failed to check matching transactions');
                    clearState();
                    return null;
                }
                return response.json();
            })
            .then((data: { count: number } | null) => {
                if (!data) return;

                // Guard: abort if the user tapped a different transaction while fetch was in flight
                const currentId = selectedTransactionIdRef.current;
                if (currentId !== selectedTransactionId) return;

                if (data.count <= 1) {
                    // Single match — apply immediately (no dialog)
                    setIsPatching(true);
                    router.patch(`/transactions/${currentId}`, {
                        category_id: categoryId,
                    }, {
                        preserveScroll: true,
                        onSuccess: () => {
                            clearState();
                            toast("Got it, I'll remember");
                        },
                        onFinish: () => setIsPatching(false),
                    });
                } else {
                    // Multiple matches — show dialog
                    setMatchCount(data.count);
                }
            })
            .catch(() => {
                toast.error('Failed to check matching transactions');
                clearState();
            });
    }, [selectedTransactionId, selectedTransactionCategoryId, selectedDescription, isPatching, clearState]);

    const handlePickerClose = useCallback(() => {
        // Only clear state if there's no pending category selection
        if (pendingCategoryId === null) {
            clearState();
        }
    }, [pendingCategoryId, clearState]);

    const pendingCategory = allCategories.find((c) => c.id === pendingCategoryId);

    const shouldReduceMotion = useReducedMotion();

    const visibleTransactions: TransactionItem[] = activeSubcategoryId
        ? transactions.filter((t) => t.categoryId === activeSubcategoryId)
        : transactions;

    return (
        <AppLayout selectedAccountIds={selectedAccountIds} onAccountsChange={handleAccountsChange} accentColor={category.color}>
            <Head title={category.name} />

            <motion.div
                className="sticky top-0 z-0"
                initial={shouldReduceMotion ? false : { opacity: 0 }}
                animate={{ opacity: 1 }}
                transition={{ duration: 0.3, ease: 'easeOut' }}
            >
                <ColorHeader
                    name={category.name}
                    color={category.color}
                    icon={category.icon}
                    totalAmountCents={totalAmountCents}
                    transactionCount={transactionCount}
                />
            </motion.div>

            <motion.div
                className="relative z-10 py-5 space-y-4"
                initial={shouldReduceMotion ? false : { opacity: 0, y: 10 }}
                animate={{ opacity: 1, y: 0 }}
                transition={{ duration: 0.3, ease: 'easeOut', delay: 0.15 }}
            >
                <CategoryMonthlyChart
                    monthlyTotals={monthlyTotals}
                    currentMonth={currentMonth}
                    accentColor={category.color}
                    categoryId={category.id}
                    selectedAccountIds={selectedAccountIds}
                    chartMonth={chartMonth}
                />

                {subcategories.length > 0 && (
                    <div className="rounded-xl overflow-hidden bg-surface/80 backdrop-blur-2xl">
                        {subcategories.map((sub, index) => (
                            <div key={sub.id}>
                                {index > 0 && (
                                    <div className="mx-4 border-t border-white/5" />
                                )}
                                <SubcategoryRow
                                    name={sub.name}
                                    amountCents={sub.amountCents}
                                    transactionCount={sub.transactionCount}
                                    isActive={activeSubcategoryId === sub.id}
                                    onClick={() => handleSubcategoryClick(sub.id)}
                                />
                            </div>
                        ))}
                    </div>
                )}

                <Deferred data="transactions" fallback={<TransactionListSkeleton />}>
                    {visibleTransactions.length > 0 ? (
                        <div className="rounded-xl overflow-hidden bg-surface/80 backdrop-blur-2xl">
                            {visibleTransactions.map((t, index) => (
                                <div key={t.id}>
                                    {index > 0 && (
                                        <div className="mx-4 border-t border-white/5" />
                                    )}
                                    <TransactionRow
                                        id={t.id}
                                        date={t.date}
                                        description={t.description}
                                        amountCents={t.amountCents}
                                        categoryId={t.categoryId}
                                        onTap={handleTransactionTap}
                                    />
                                </div>
                            ))}
                        </div>
                    ) : (
                        <div className="py-8 text-center">
                            <p className="text-sm text-text-tertiary">
                                No transactions for this category
                            </p>
                        </div>
                    )}
                </Deferred>
            </motion.div>

            <CategoryPicker
                categories={allCategories}
                currentCategoryId={selectedTransactionCategoryId ?? 0}
                isOpen={selectedTransactionId !== null && pendingCategoryId === null}
                onClose={handlePickerClose}
                onSelect={handleCategorySelect}
                triggerRef={pickerTriggerRef}
            />

            <ApplyRuleConfirmDialog
                isOpen={matchCount !== null && matchCount > 1 && pendingCategoryId !== null}
                description={selectedDescription ?? ''}
                categoryName={pendingCategory?.name ?? ''}
                categoryColor={pendingCategory?.color ?? ''}
                matchCount={matchCount ?? 0}
                isLoading={isPatching}
                onApplyOne={() => patchTransaction(false)}
                onApplyAll={() => patchTransaction(true)}
                onCancel={clearState}
            />
        </AppLayout>
    );
}
