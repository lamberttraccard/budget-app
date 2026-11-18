import Amount from '@/components/Amount';

export type TransactionTapData = {
    id: number;
    categoryId: number;
    description: string;
    element: HTMLElement;
};

type Props = {
    id: number;
    date: string;
    description: string;
    amountCents: number;
    categoryId: number;
    onTap?: (data: TransactionTapData) => void;
};

export default function TransactionRow({
    id,
    date,
    description,
    amountCents,
    categoryId,
    onTap,
}: Props) {
    const day = new Date(date + 'T00:00:00');

    return (
        <button
            type="button"
            onClick={(e) =>
                onTap?.({
                    id,
                    categoryId,
                    description,
                    element: e.currentTarget,
                })
            }
            className="flex min-h-[44px] w-full cursor-pointer items-center gap-3 px-4 py-2.5 text-left transition-colors hover:bg-white/[0.04]"
        >
            <span className="flex size-10 flex-shrink-0 flex-col items-center justify-center rounded-xl bg-white/[0.07] leading-none">
                <span className="text-sm text-white tabular-nums">
                    {day.getDate()}
                </span>
                <span className="mt-0.5 text-[10px] text-white/50">
                    {day.toLocaleDateString('en-US', { month: 'short' })}
                </span>
            </span>
            <span className="min-w-0 flex-1 truncate text-sm text-white">
                {description}
            </span>
            <Amount
                cents={amountCents}
                className="ml-auto text-sm font-medium text-white"
            />
        </button>
    );
}
