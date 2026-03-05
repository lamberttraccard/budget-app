import { Deferred, Head, router } from '@inertiajs/react';
import { useCallback, useMemo, useState } from 'react';
import AmbientCircle from '@/components/AmbientCircle';
import RecurringMerchantCard from '@/components/RecurringMerchantCard';
import { Skeleton } from '@/components/ui/skeleton';
import AppLayout from '@/layouts/app-layout';
import { formatAbsAmount } from '@/lib/formatAmount';
import type { RecurringMerchant, RecurringProps } from '@/types';

type StatusFilter = 'all' | 'active' | 'inactive';

const EMPTY_MERCHANTS: RecurringMerchant[] = [];

const filterOptions: { value: StatusFilter; label: string }[] = [
    { value: 'all', label: 'All' },
    { value: 'active', label: 'Active' },
    { value: 'inactive', label: 'Inactive' },
];

function RecurringPageSkeleton() {
    return (
        <div className="space-y-3">
            {Array.from({ length: 4 }).map((_, i) => (
                <div
                    key={i}
                    className="bg-surface/80 backdrop-blur-2xl border border-white/[0.06] rounded-xl p-4 flex items-center gap-3"
                >
                    <Skeleton className="size-2 rounded-full shrink-0" />
                    <div className="flex-1 min-w-0 space-y-2">
                        <Skeleton className="h-4 w-32" />
                        <Skeleton className="h-3 w-20" />
                    </div>
                    <Skeleton className="h-4 w-16 shrink-0" />
                </div>
            ))}
        </div>
    );
}

export default function RecurringPage({
    recurringMerchants,
    selectedAccountIds,
}: RecurringProps) {
    const [expandedMerchant, setExpandedMerchant] = useState<string | null>(null);
    const [statusFilter, setStatusFilter] = useState<StatusFilter>('all');

    const toggleMerchant = useCallback((merchantName: string) => {
        setExpandedMerchant((prev) => (prev === merchantName ? null : merchantName));
    }, []);

    const handleAccountsChange = useCallback((accountIds: number[]) => {
        router.get('/recurring', accountIds.length > 0 ? { accounts: accountIds.join(',') } : {}, {
            preserveState: true,
            replace: true,
            only: ['recurringMerchants', 'selectedAccountIds'],
        });
    }, []);

    const merchants = recurringMerchants ?? EMPTY_MERCHANTS;

    const filteredMerchants = useMemo(() => {
        if (statusFilter === 'all') return merchants;
        return merchants.filter((m) =>
            statusFilter === 'active' ? m.isActive : !m.isActive,
        );
    }, [merchants, statusFilter]);

    const { incomeCents, expensesCents, incomeCount, expensesCount } = useMemo(() => {
        let inc = 0, exp = 0, incN = 0, expN = 0;
        for (const m of filteredMerchants) {
            if (m.typicalAmountCents >= 0) { inc += m.typicalAmountCents; incN++; }
            else { exp += Math.abs(m.typicalAmountCents); expN++; }
        }
        return { incomeCents: inc, expensesCents: exp, incomeCount: incN, expensesCount: expN };
    }, [filteredMerchants]);

    return (
        <AppLayout onAccountsChange={handleAccountsChange}>
            <Head title="Recurring" />

            <AmbientCircle />

            <div className="relative py-6 space-y-4">
                {/* Header */}
                <div className="flex items-center justify-between gap-3">
                    <h1 className="text-lg font-semibold text-white">Recurring</h1>
                    <div role="radiogroup" aria-label="Filter by status" className="flex rounded-lg bg-surface-elevated/60 p-0.5">
                        {filterOptions.map((option) => (
                            <button
                                key={option.value}
                                type="button"
                                role="radio"
                                aria-checked={statusFilter === option.value}
                                onClick={() => setStatusFilter(option.value)}
                                className={`px-2.5 py-1 text-xs font-medium rounded-md transition-colors ${
                                    statusFilter === option.value
                                        ? 'bg-white/10 text-white'
                                        : 'text-text-tertiary hover:text-text-secondary'
                                }`}
                            >
                                {option.label}
                            </button>
                        ))}
                    </div>
                </div>

                {/* Monthly totals */}
                <Deferred data="recurringMerchants" fallback={<div className="flex gap-3"><Skeleton className="h-[88px] flex-1 rounded-xl" /><Skeleton className="h-[88px] flex-1 rounded-xl" /></div>}>
                    {filteredMerchants.length > 0 && (
                        <div className="flex gap-3">
                            <div className="relative flex-1 overflow-hidden rounded-xl p-4 bg-surface/60 backdrop-blur-2xl border border-white/[0.06]">
                                <p className="text-xs text-text-secondary mb-1">Income</p>
                                <p className="text-lg font-semibold tabular-nums text-[#10B981]">
                                    {formatAbsAmount(incomeCents)}
                                </p>
                                <p className="text-xs text-text-tertiary mt-1">
                                    {incomeCount} recurring
                                </p>
                            </div>
                            <div className="relative flex-1 overflow-hidden rounded-xl p-4 bg-surface/60 backdrop-blur-2xl border border-white/[0.06]">
                                <p className="text-xs text-text-secondary mb-1">Fixed charges</p>
                                <p className="text-lg font-semibold tabular-nums text-[#FB7185]">
                                    {formatAbsAmount(expensesCents)}
                                </p>
                                <p className="text-xs text-text-tertiary mt-1">
                                    {expensesCount} recurring
                                </p>
                            </div>
                        </div>
                    )}
                </Deferred>

                {/* Recurring merchant list */}
                <Deferred data="recurringMerchants" fallback={<RecurringPageSkeleton />}>
                    {filteredMerchants.length > 0 ? (
                        <div className="space-y-3">
                            {filteredMerchants.map((merchant) => (
                                <RecurringMerchantCard
                                    key={merchant.merchantName}
                                    {...merchant}
                                    isExpanded={expandedMerchant === merchant.merchantName}
                                    onToggle={() => toggleMerchant(merchant.merchantName)}
                                />
                            ))}
                        </div>
                    ) : (
                        <div className="py-12 text-center">
                            <p className="text-sm text-text-tertiary">
                                {statusFilter === 'all'
                                    ? 'No recurring transactions detected yet'
                                    : `No ${statusFilter} recurring transactions`}
                            </p>
                        </div>
                    )}
                </Deferred>
            </div>
        </AppLayout>
    );
}
