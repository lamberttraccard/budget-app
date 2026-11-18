import { ChevronDown } from 'lucide-react';
import { formatAbsAmount } from '@/lib/formatAmount';
import { formatDate } from '@/lib/formatDate';
import type { RecurringMerchant } from '@/types';

type Props = RecurringMerchant & {
    isExpanded: boolean;
    onToggle: () => void;
};

function formatMonth(yearMonth: string): string {
    const [year, month] = yearMonth.split('-');
    const date = new Date(Number(year), Number(month) - 1);
    return date.toLocaleDateString('en-US', {
        month: 'short',
        year: 'numeric',
    });
}

export default function RecurringMerchantCard({
    merchantName,
    typicalAmountCents,
    frequency,
    lastSeenMonth,
    amountVaries,
    minAmountCents,
    maxAmountCents,
    transactions,
    isExpanded,
    onToggle,
}: Props) {
    return (
        <div>
            <button
                type="button"
                onClick={onToggle}
                className="flex min-h-[44px] w-full cursor-pointer items-center gap-3 px-4 py-3 text-left transition-colors hover:bg-white/[0.04]"
            >
                <span className="min-w-0 flex-1">
                    <span className="block truncate text-[15px] text-white">
                        {merchantName}
                    </span>
                </span>
                <span className="shrink-0 text-right">
                    <span className="block text-sm font-medium text-white tabular-nums">
                        {formatAbsAmount(typicalAmountCents)}
                    </span>
                    {amountVaries && (
                        <span className="block text-[10px] text-text-secondary tabular-nums">
                            {formatAbsAmount(maxAmountCents)} –{' '}
                            {formatAbsAmount(minAmountCents)}
                        </span>
                    )}
                </span>
                <span className="ml-1 flex shrink-0 flex-col items-end">
                    <span className="text-xs text-white/60">{frequency}</span>
                    <span className="text-[10px] text-white/40">
                        {formatMonth(lastSeenMonth)}
                    </span>
                </span>
                <ChevronDown
                    className={`size-4 shrink-0 text-white/40 transition-transform duration-200 ${
                        isExpanded ? 'rotate-180' : ''
                    }`}
                />
            </button>

            {isExpanded && transactions.length > 0 && (
                <div className="border-t border-white/[0.06] bg-black/10 px-4 pb-3">
                    {transactions.map((tx) => (
                        <div
                            key={tx.id}
                            className="flex min-h-[36px] items-center gap-3 py-2"
                        >
                            <span className="w-16 shrink-0 text-xs text-text-tertiary">
                                {formatDate(tx.date)}
                            </span>
                            <span className="min-w-0 flex-1">
                                {tx.accountName && (
                                    <span className="rounded-full bg-white/[0.07] px-2 py-0.5 text-[10px] text-white/60">
                                        {tx.accountName}
                                    </span>
                                )}
                            </span>
                            <span className="shrink-0 text-xs font-medium text-white tabular-nums">
                                {formatAbsAmount(tx.amountCents)}
                            </span>
                        </div>
                    ))}
                </div>
            )}
        </div>
    );
}
