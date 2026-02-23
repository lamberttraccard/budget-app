import type { TransactionTapData } from '@/components/TransactionRow';
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
    onTap: (data: TransactionTapData) => void;
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
            onClick={(e) => onTap({ id, categoryId, description, element: e.currentTarget })}
            className="flex w-full items-center gap-3 px-4 py-2.5 min-h-[44px] text-left
                       hover:bg-surface-elevated rounded-lg transition-colors cursor-pointer"
        >
            <span
                className="size-2 rounded-full shrink-0"
                style={{ backgroundColor: categoryColor }}
            />
            <span className="flex-1 min-w-0">
                <span className="flex items-center justify-between gap-2">
                    <span className="text-sm text-white truncate">
                        {description}
                    </span>
                    <span className="flex items-center gap-2 shrink-0">
                        <span className="text-xs text-text-tertiary">
                            {formatDate(date)}
                        </span>
                        <span className="text-sm font-semibold tabular-nums text-white">
                            {formatAbsAmount(amountCents)}
                        </span>
                    </span>
                </span>
                <span className="flex items-center justify-between mt-0.5">
                    <span className="text-xs text-text-tertiary truncate min-w-0">
                        {categoryName}
                    </span>
                    {accountName && (
                        <span className="text-[10px] text-text-tertiary bg-surface-elevated rounded-full px-1.5 py-0.5 shrink-0 truncate max-w-[100px]">
                            {accountName}
                        </span>
                    )}
                </span>
            </span>
        </button>
    );
}
