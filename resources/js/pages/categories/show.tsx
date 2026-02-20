import { Head, router } from '@inertiajs/react';
import { useState } from 'react';
import AppLayout from '@/layouts/app-layout';
import ColorHeader from '@/components/ColorHeader';
import SubcategoryRow from '@/components/SubcategoryRow';
import TransactionRow from '@/components/TransactionRow';
import type { CategoryShowProps, TransactionItem } from '@/types';

export default function CategoryShow({
    category,
    subcategories,
    transactions,
    totalAmountCents,
    transactionCount,
    currentMonth,
}: CategoryShowProps) {
    const [activeSubcategoryId, setActiveSubcategoryId] = useState<number | null>(null);

    const handleBack = () => {
        // Use history.back() for scroll preservation when navigating from Dashboard;
        // fall back to router.visit if the user arrived directly (no in-app history)
        if (window.history.length > 1 && document.referrer.includes(window.location.origin)) {
            window.history.back();
        } else {
            router.visit('/dashboard');
        }
    };

    const handleSubcategoryClick = (subcategoryId: number) => {
        setActiveSubcategoryId(
            activeSubcategoryId === subcategoryId ? null : subcategoryId,
        );
    };

    const visibleTransactions: TransactionItem[] = activeSubcategoryId
        ? transactions.filter((t) => t.categoryId === activeSubcategoryId)
        : transactions;

    return (
        <AppLayout>
            <Head title={category.name} />

            <ColorHeader
                name={category.name}
                color={category.color}
                totalAmountCents={totalAmountCents}
                transactionCount={transactionCount}
                onBack={handleBack}
            />

            <div className="py-5 space-y-4">
                {subcategories.length > 0 && (
                    <div className="bg-surface rounded-xl overflow-hidden">
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

                {visibleTransactions.length > 0 ? (
                    <div className="bg-surface rounded-xl overflow-hidden">
                        {visibleTransactions.map((t, index) => (
                            <div key={t.id}>
                                {index > 0 && (
                                    <div className="mx-4 border-t border-white/5" />
                                )}
                                <TransactionRow
                                    date={t.date}
                                    description={t.description}
                                    amountCents={t.amountCents}
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
            </div>
        </AppLayout>
    );
}
