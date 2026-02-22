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
    categoryName,
    categoryColor,
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
        <div className="bg-surface/80 backdrop-blur-2xl border border-white/[0.06] rounded-xl overflow-hidden">
            <button
                type="button"
                onClick={onToggle}
                className="flex w-full items-center gap-3 px-4 py-3 min-h-[44px] text-left cursor-pointer"
            >
                <span
                    className="size-2 rounded-full shrink-0"
                    style={{ backgroundColor: categoryColor }}
                />
                <span className="flex-1 min-w-0">
                    <span className="block text-[15px] font-semibold text-white truncate">
                        {merchantName}
                    </span>
                    <span className="block text-xs text-text-secondary truncate">
                        {categoryName}
                    </span>
                </span>
                <span className="text-right shrink-0">
                    <span className="block text-sm font-semibold tabular-nums text-white">
                        {formatAbsAmount(typicalAmountCents)}
                    </span>
                    {amountVaries && (
                        <span className="block text-[10px] text-text-secondary tabular-nums">
                            {formatAbsAmount(maxAmountCents)} – {formatAbsAmount(minAmountCents)}
                        </span>
                    )}
                </span>
                <span className="flex flex-col items-end shrink-0 ml-1">
                    <span className="text-xs text-text-secondary">{frequency}</span>
                    <span className="text-[10px] text-text-tertiary">{formatMonth(lastSeenMonth)}</span>
                </span>
                <ChevronDown
                    className={`size-4 text-text-tertiary shrink-0 transition-transform duration-200 ${
                        isExpanded ? 'rotate-180' : ''
                    }`}
                />
            </button>

            {isExpanded && transactions.length > 0 && (
                <div className="border-t border-white/5 px-4 pb-3">
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
                                    <span className="text-[10px] text-text-tertiary bg-surface-elevated rounded-full px-1.5 py-0.5">
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
