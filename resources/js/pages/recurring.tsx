import { Head, router } from '@inertiajs/react';
import { useCallback, useState } from 'react';
import RecurringMerchantCard from '@/components/RecurringMerchantCard';
import AppLayout from '@/layouts/app-layout';
import type { RecurringProps } from '@/types';

export default function RecurringPage({
    recurringMerchants,
    currentAccount,
}: RecurringProps) {
    const [expandedMerchant, setExpandedMerchant] = useState<string | null>(null);

    const toggleMerchant = useCallback((merchantName: string) => {
        setExpandedMerchant((prev) => (prev === merchantName ? null : merchantName));
    }, []);

    const handleAccountChange = useCallback((account: string) => {
        router.get('/recurring', { account }, { preserveState: true, replace: true });
    }, []);

    return (
        <AppLayout currentAccount={currentAccount ?? undefined} onAccountChange={handleAccountChange}>
            <Head title="Recurring" />

            <div className="py-6 space-y-4">
                {/* Header */}
                <div>
                    <h1 className="text-lg font-semibold text-white">Recurring</h1>
                    {recurringMerchants.length > 0 && (
                        <p className="text-xs text-text-secondary">
                            {recurringMerchants.length} recurring transaction{recurringMerchants.length === 1 ? '' : 's'}
                        </p>
                    )}
                </div>

                {/* Recurring merchant list */}
                {recurringMerchants.length > 0 ? (
                    <div className="space-y-3">
                        {recurringMerchants.map((merchant) => (
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
            </div>
        </AppLayout>
    );
}
