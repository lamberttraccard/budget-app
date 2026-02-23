import { Deferred, Head, router } from '@inertiajs/react';
import { useCallback, useState } from 'react';
import AmbientCircle from '@/components/AmbientCircle';
import RecurringMerchantCard from '@/components/RecurringMerchantCard';
import { Skeleton } from '@/components/ui/skeleton';
import AppLayout from '@/layouts/app-layout';
import type { RecurringProps } from '@/types';

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

    const merchants = recurringMerchants ?? [];

    return (
        <AppLayout selectedAccountIds={selectedAccountIds} onAccountsChange={handleAccountsChange}>
            <Head title="Recurring" />

            <AmbientCircle />

            <div className="relative py-6 space-y-4">
                {/* Header */}
                <div>
                    <h1 className="text-lg font-semibold text-white">Recurring</h1>
                </div>

                {/* Recurring merchant list */}
                <Deferred data="recurringMerchants" fallback={<RecurringPageSkeleton />}>
                    {merchants.length > 0 && (
                        <p className="text-xs text-text-secondary -mt-2">
                            {merchants.length} recurring transaction{merchants.length === 1 ? '' : 's'}
                        </p>
                    )}
                    {merchants.length > 0 ? (
                        <div className="space-y-3">
                            {merchants.map((merchant) => (
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
                                No recurring transactions detected yet
                            </p>
                        </div>
                    )}
                </Deferred>
            </div>
        </AppLayout>
    );
}
