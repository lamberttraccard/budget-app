import { Head } from '@inertiajs/react';
import AppLayout from '@/layouts/app-layout';
import SummaryWidget from '@/components/SummaryWidget';
import CategoryCard from '@/components/CategoryCard';
import type { CategoryTotal, DashboardProps } from '@/types';

function formatMonthLabel(month: string): string {
    const [year, m] = month.split('-');
    const date = new Date(Number(year), Number(m) - 1);
    return date.toLocaleDateString('en-US', { month: 'long', year: 'numeric' });
}

export default function Dashboard({ categoryTotals, incomeCents, expensesCents, currentMonth }: DashboardProps) {
    return (
        <AppLayout>
            <Head title="Budget" />
            <div className="py-6 space-y-5">
                <SummaryWidget incomeCents={incomeCents} expensesCents={expensesCents} />

                {categoryTotals.length === 0 ? (
                    <div className="rounded-xl bg-surface p-6 text-center">
                        <p className="text-sm text-text-secondary">
                            No transactions for {formatMonthLabel(currentMonth)}.
                            Import your first CSV to get started.
                        </p>
                    </div>
                ) : (
                    <div className="space-y-3">
                        {categoryTotals.map((cat: CategoryTotal) => (
                            <CategoryCard
                                key={cat.id}
                                name={cat.name}
                                color={cat.color}
                                amountCents={cat.amountCents}
                                transactionCount={cat.transactionCount}
                            />
                        ))}
                    </div>
                )}
            </div>
        </AppLayout>
    );
}
