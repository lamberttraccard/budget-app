import { formatAbsAmount } from '@/lib/formatAmount';
import { formatDate } from '@/lib/formatDate';

type Props = {
    date: string;
    description: string;
    amountCents: number;
};

export default function TransactionRow({ date, description, amountCents }: Props) {
    return (
        <div className="flex items-center gap-3 px-4 py-2.5 min-h-[44px]">
            <span className="w-12 flex-shrink-0 text-xs text-text-tertiary">
                {formatDate(date)}
            </span>
            <span className="flex-1 min-w-0 text-sm text-white truncate">
                {description}
            </span>
            <span className="text-sm font-semibold tabular-nums text-white ml-auto">
                {formatAbsAmount(amountCents)}
            </span>
        </div>
    );
}
