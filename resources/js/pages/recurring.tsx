import { Deferred, Head, router } from '@inertiajs/react';
import { useCallback, useMemo, useState } from 'react';
import Amount from '@/components/Amount';
import RecurringMerchantCard from '@/components/RecurringMerchantCard';
import { Skeleton } from '@/components/ui/skeleton';
import AppLayout from '@/layouts/app-layout';
import { formatAbsAmount } from '@/lib/formatAmount';
import type { RecurringMerchant, RecurringProps } from '@/types';

type StatusFilter = 'active' | 'inactive';

const EMPTY_MERCHANTS: RecurringMerchant[] = [];

const filterOptions: { value: StatusFilter; label: string }[] = [
    { value: 'active', label: 'Active' },
    { value: 'inactive', label: 'Inactive' },
];

function RecurringPageSkeleton() {
    return (
        <div className="space-y-3">
            {Array.from({ length: 4 }).map((_, i) => (
                <div
                    key={i}
                    className="glass rounded-panel p-4 flex items-center gap-3"
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
}: RecurringProps) {
    const [expandedMerchant, setExpandedMerchant] = useState<string | null>(null);
    const [statusFilter, setStatusFilter] = useState<StatusFilter>('active');

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

    const categoryGroups = useMemo(() => {
        const groups = new Map<string, { name: string; color: string; totalCents: number; merchants: RecurringMerchant[] }>();
        for (const m of filteredMerchants) {
            const key = m.categoryName;
            const group = groups.get(key);
            if (group) {
                group.totalCents += Math.abs(m.typicalAmountCents);
                group.merchants.push(m);
            } else {
                groups.set(key, { name: key, color: m.categoryColor, totalCents: Math.abs(m.typicalAmountCents), merchants: [m] });
            }
        }
        return [...groups.values()].sort((a, b) => b.totalCents - a.totalCents);
    }, [filteredMerchants]);

    return (
        <AppLayout onAccountsChange={handleAccountsChange}>
            <Head title="Recurring" />

            <div className="pt-4 pb-6 space-y-5">
                {/* Header */}
                <div className="flex items-center justify-between gap-3">
                    <h1 className="text-3xl font-light tracking-tight text-white">Recurring</h1>
                    <div role="radiogroup" aria-label="Filter by status" className="glass flex rounded-full p-1">
                        {filterOptions.map((option) => (
                            <button
                                key={option.value}
                                type="button"
                                role="radio"
                                aria-checked={statusFilter === option.value}
                                onClick={() => setStatusFilter(option.value)}
                                className={`px-3 py-1 text-xs font-medium rounded-full transition-colors ${
                                    statusFilter === option.value
                                        ? 'bg-foreground text-background'
                                        : 'text-white/55 hover:text-white'
                                }`}
                            >
                                {option.label}
                            </button>
                        ))}
                    </div>
                </div>

                {/* Monthly totals */}
                <Deferred data="recurringMerchants" fallback={<div className="flex gap-3"><Skeleton className="h-[96px] flex-1 rounded-panel" /><Skeleton className="h-[96px] flex-1 rounded-panel" /></div>}>
                    {filteredMerchants.length > 0 && (
                        <div className="flex gap-3">
                            <div className="glass flex-1 min-w-0 rounded-panel p-4">
                                <p className="flex items-center gap-2 text-xs text-white/60">
                                    <span className="size-1.5 rounded-full bg-[#34D399] shadow-[0_0_8px_#34D399]" />
                                    Income
                                </p>
                                <Amount cents={incomeCents} className="mt-1.5 block text-2xl font-light tracking-tight text-white" />
                                <p className="text-xs text-white/50 mt-1">
                                    {incomeCount} recurring
                                </p>
                            </div>
                            <div className="glass flex-1 min-w-0 rounded-panel p-4">
                                <p className="flex items-center gap-2 text-xs text-white/60">
                                    <span className="size-1.5 rounded-full bg-[#FB7185] shadow-[0_0_8px_#FB7185]" />
                                    Fixed charges
                                </p>
                                <Amount cents={expensesCents} className="mt-1.5 block text-2xl font-light tracking-tight text-white" />
                                <p className="text-xs text-white/50 mt-1">
                                    {expensesCount} recurring
                                </p>
                            </div>
                        </div>
                    )}
                </Deferred>

                {/* Recurring merchant list grouped by category */}
                <Deferred data="recurringMerchants" fallback={<RecurringPageSkeleton />}>
                    {filteredMerchants.length > 0 ? (
                        <div className="space-y-5">
                            {categoryGroups.map((group) => (
                                <div key={group.name} className="space-y-2">
                                    <div className="flex items-center justify-between px-1">
                                        <div className="flex items-center gap-2">
                                            <span
                                                className="size-1.5 rounded-full"
                                                style={{ backgroundColor: group.color, boxShadow: `0 0 8px ${group.color}` }}
                                            />
                                            <span className="text-xs text-white/60">{group.name}</span>
                                        </div>
                                        <span className="text-xs tabular-nums text-white/60">
                                            {formatAbsAmount(group.totalCents)}
                                        </span>
                                    </div>
                                    <div className="glass divide-y divide-white/[0.06] overflow-hidden rounded-panel">
                                        {group.merchants.map((merchant) => (
                                            <RecurringMerchantCard
                                                key={merchant.merchantName}
                                                {...merchant}
                                                isExpanded={expandedMerchant === merchant.merchantName}
                                                onToggle={() => toggleMerchant(merchant.merchantName)}
                                            />
                                        ))}
                                    </div>
                                </div>
                            ))}
                        </div>
                    ) : (
                        <div className="py-12 text-center">
                            <p className="text-sm text-text-tertiary">
                                {`No ${statusFilter} recurring transactions`}
                            </p>
                        </div>
                    )}
                </Deferred>
            </div>
        </AppLayout>
    );
}
