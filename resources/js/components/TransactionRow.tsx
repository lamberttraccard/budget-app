import { formatAbsAmount } from '@/lib/formatAmount';
import { formatDate } from '@/lib/formatDate';

type Props = {
    id: number;
    date: string;
    description: string;
    amountCents: number;
    categoryId: number;
    onTap?: (transactionId: number, categoryId: number, element: HTMLElement) => void;
};

export default function TransactionRow({ id, date, description, amountCents, categoryId, onTap }: Props) {
    return (
        <button
            type="button"
            onClick={(e) => onTap?.(id, categoryId, e.currentTarget)}
            className="flex w-full items-center gap-3 px-4 py-2.5 min-h-[44px] text-left
                       hover:bg-surface-elevated rounded-lg transition-colors cursor-pointer"
        >
            <span className="w-12 flex-shrink-0 text-xs text-text-tertiary">
                {formatDate(date)}
            </span>
            <span className="flex-1 min-w-0 text-sm text-white truncate">
                {description}
            </span>
            <span className="text-sm font-semibold tabular-nums text-white ml-auto">
                {formatAbsAmount(amountCents)}
            </span>
        </button>
    );
}
