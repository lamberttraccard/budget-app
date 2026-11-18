import Amount from '@/components/Amount';
import type { TransactionTapData } from '@/components/TransactionRow';
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
            onClick={(e) =>
                onTap({ id, categoryId, description, element: e.currentTarget })
            }
            className="flex min-h-[44px] w-full cursor-pointer items-center gap-3 px-4 py-2.5 text-left transition-colors hover:bg-white/[0.04]"
        >
            <span
                className="size-2 shrink-0 rounded-full"
                style={{
                    backgroundColor: categoryColor,
                    boxShadow: `0 0 8px ${categoryColor}`,
                }}
            />
            <span className="min-w-0 flex-1">
                <span className="flex items-center justify-between gap-2">
                    <span className="truncate text-sm text-white">
                        {description}
                    </span>
                    <span className="flex shrink-0 items-center gap-2">
                        <span className="text-xs text-white/40">
                            {formatDate(date)}
                        </span>
                        <Amount
                            cents={amountCents}
                            className="text-sm font-medium text-white"
                        />
                    </span>
                </span>
                <span className="mt-0.5 flex items-center justify-between">
                    <span className="min-w-0 truncate text-xs text-white/50">
                        {categoryName}
                    </span>
                    {accountName && (
                        <span className="max-w-[100px] shrink-0 truncate rounded-full bg-white/[0.07] px-2 py-0.5 text-[10px] text-white/60">
                            {accountName}
                        </span>
                    )}
                </span>
            </span>
        </button>
    );
}
