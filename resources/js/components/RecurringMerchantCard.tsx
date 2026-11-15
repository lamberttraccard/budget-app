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
    return date.toLocaleDateString('en-US', { month: 'short', year: 'numeric' });
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
                className="flex w-full items-center gap-3 px-4 py-3 min-h-[44px] text-left cursor-pointer transition-colors hover:bg-white/[0.04]"
            >
                <span className="flex-1 min-w-0">
                    <span className="block text-[15px] text-white truncate">
                        {merchantName}
                    </span>
                </span>
                <span className="text-right shrink-0">
                    <span className="block text-sm font-medium tabular-nums text-white">
                        {formatAbsAmount(typicalAmountCents)}
                    </span>
                    {amountVaries && (
                        <span className="block text-[10px] text-text-secondary tabular-nums">
                            {formatAbsAmount(maxAmountCents)} – {formatAbsAmount(minAmountCents)}
                        </span>
                    )}
                </span>
                <span className="flex flex-col items-end shrink-0 ml-1">
                    <span className="text-xs text-white/60">{frequency}</span>
                    <span className="text-[10px] text-white/40">{formatMonth(lastSeenMonth)}</span>
                </span>
                <ChevronDown
                    className={`size-4 text-white/40 shrink-0 transition-transform duration-200 ${
                        isExpanded ? 'rotate-180' : ''
                    }`}
                />
            </button>

            {isExpanded && transactions.length > 0 && (
                <div className="border-t border-white/[0.06] bg-black/10 px-4 pb-3">
                    {transactions.map((tx) => (
                        <div
                            key={tx.id}
                            className="flex items-center gap-3 py-2 min-h-[36px]"
                        >
                            <span className="text-xs text-text-tertiary w-16 shrink-0">
                                {formatDate(tx.date)}
                            </span>
                            <span className="flex-1 min-w-0">
                                {tx.accountName && (
                                    <span className="text-[10px] text-white/60 bg-white/[0.07] rounded-full px-2 py-0.5">
                                        {tx.accountName}
                                    </span>
                                )}
                            </span>
                            <span className="text-xs font-medium tabular-nums text-white shrink-0">
                                {formatAbsAmount(tx.amountCents)}
                            </span>
                        </div>
                    ))}
                </div>
            )}
        </div>
    );
}
