import { Deferred, Head, router, usePage } from '@inertiajs/react';
import { motion, useReducedMotion } from 'motion/react';
import { useCallback, useRef, useState } from 'react';
import { toast } from 'sonner';
import CategoryMonthlyChart from '@/components/CategoryMonthlyChart';
import CategoryPicker from '@/components/CategoryPicker';
import ColorHeader from '@/components/ColorHeader';
import SubcategoryRow from '@/components/SubcategoryRow';
import TransactionRow from '@/components/TransactionRow';
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
    currentAccount,
    monthlyTotals,
    chartMonth,
}: CategoryShowProps) {
    const { allCategories } = usePage<SharedProps>().props;
    const [activeSubcategoryId, setActiveSubcategoryId] = useState<number | null>(null);
    const [selectedTransactionId, setSelectedTransactionId] = useState<number | null>(null);
    const [selectedTransactionCategoryId, setSelectedTransactionCategoryId] = useState<number | null>(null);
    const [isPatching, setIsPatching] = useState(false);
    const pickerTriggerRef = useRef<HTMLSpanElement>(null);

    const handleAccountChange = useCallback((account: string) => {
        router.get('/', { account });
    }, []);

    const handleSubcategoryClick = (subcategoryId: number) => {
        setActiveSubcategoryId(
            activeSubcategoryId === subcategoryId ? null : subcategoryId,
        );
    };

    const handleTransactionTap = useCallback((transactionId: number, categoryId: number, element: HTMLElement) => {
        if (isPatching) return;
        setSelectedTransactionId(transactionId);
        setSelectedTransactionCategoryId(categoryId);

        // Position the desktop dropdown anchor at the clicked row
        if (pickerTriggerRef.current) {
            const rect = element.getBoundingClientRect();
            Object.assign(pickerTriggerRef.current.style, {
                top: `${rect.bottom}px`,
                left: `${rect.left}px`,
                width: `${rect.width}px`,
            });
        }
    }, [isPatching]);

    const handleCategorySelect = useCallback((categoryId: number) => {
        if (selectedTransactionId === null || isPatching) return;
        setIsPatching(true);
        router.patch(`/transactions/${selectedTransactionId}`, {
            category_id: categoryId,
        }, {
            preserveScroll: true,
            onSuccess: () => {
                setSelectedTransactionId(null);
                setSelectedTransactionCategoryId(null);
                toast("Got it, I'll remember");
            },
            onFinish: () => setIsPatching(false),
        });
    }, [selectedTransactionId, isPatching]);

    const shouldReduceMotion = useReducedMotion();

    const visibleTransactions: TransactionItem[] = activeSubcategoryId
        ? transactions.filter((t) => t.categoryId === activeSubcategoryId)
        : transactions;

    return (
        <AppLayout currentAccount={currentAccount} onAccountChange={handleAccountChange} accentColor={category.color}>
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
                    currentAccount={currentAccount}
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
                isOpen={selectedTransactionId !== null}
                onClose={() => {
                    setSelectedTransactionId(null);
                    setSelectedTransactionCategoryId(null);
                }}
                onSelect={handleCategorySelect}
                triggerRef={pickerTriggerRef}
            />
        </AppLayout>
    );
}
