import { Link } from '@inertiajs/react';
import { formatAbsAmount } from '@/lib/formatAmount';

type Props = {
    id: number;
    name: string;
    color: string;
    amountCents: number;
    transactionCount: number;
    currentMonth: string;
    currentAccount: string;
};

export default function CategoryCard({ id, name, color, amountCents, transactionCount, currentMonth, currentAccount }: Props) {
    return (
        <Link
            href={`/categories/${id}?month=${currentMonth}&account=${currentAccount}`}
            className="flex items-center gap-4 bg-surface rounded-xl p-4 transition-colors hover:bg-surface-elevated"
        >
            <div
                className="w-12 h-12 rounded-xl flex-shrink-0"
                style={{ backgroundColor: color }}
                aria-hidden="true"
            />
            <div className="flex-1 min-w-0">
                <p className="text-sm font-normal text-white truncate">{name}</p>
                <p className="text-xs text-text-secondary">
                    {transactionCount} transaction{transactionCount !== 1 ? 's' : ''}
                </p>
            </div>
            <p
                className="text-2xl font-semibold tabular-nums ml-auto"
                style={{ color }}
            >
                {formatAbsAmount(amountCents)}
            </p>
        </Link>
    );
}
