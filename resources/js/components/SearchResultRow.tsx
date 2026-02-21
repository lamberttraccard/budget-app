import { formatAbsAmount } from '@/lib/formatAmount';
import { formatDate } from '@/lib/formatDate';

type Props = {
    id: number;
    date: string;
    description: string;
    amountCents: number;
    categoryId: number;
    categoryName: string;
    categoryColor: string;
    accountName: string;
    onTap: (transactionId: number, categoryId: number, element: HTMLElement) => void;
};

export default function SearchResultRow({
    id,
    date,
    description,
    amountCents,
    categoryId,
    categoryName,
    categoryColor,
    accountName,
    onTap,
}: Props) {
    return (
        <button
            type="button"
            onClick={(e) => onTap(id, categoryId, e.currentTarget)}
            className="flex w-full items-center gap-3 px-4 py-2.5 min-h-[44px] text-left
                       hover:bg-surface-elevated rounded-lg transition-colors cursor-pointer"
        >
            <span
                className="size-2 rounded-full shrink-0"
                style={{ backgroundColor: categoryColor }}
            />
            <span className="flex-1 min-w-0">
                <span className="block text-sm text-white truncate">
                    {description}
                </span>
                <span className="block text-xs text-text-tertiary truncate">
                    {categoryName}
                </span>
            </span>
            <span className="text-xs text-text-tertiary shrink-0">
                {formatDate(date)}
            </span>
            {accountName && (
                <span className="text-[10px] text-text-tertiary bg-surface-elevated rounded-full px-1.5 py-0.5 shrink-0">
                    {accountName}
                </span>
            )}
            <span className="text-sm font-semibold tabular-nums text-white shrink-0">
                {formatAbsAmount(amountCents)}
            </span>
        </button>
    );
}
