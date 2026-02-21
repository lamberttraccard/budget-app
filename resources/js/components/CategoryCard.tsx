import { Link } from '@inertiajs/react';
import { formatAbsAmount } from '@/lib/formatAmount';

type Props = {
    id: number;
    name: string;
    color: string;
    amountCents: number;
    transactionCount: number;
    trendPercentage?: number | null;
    currentMonth: string;
    currentAccount: string;
};

function TrendIndicator({ value }: { value: number | null }) {
    if (value === null) {
        return <span className="text-xs text-text-tertiary">new</span>;
    }
    if (value === 0) {
        return <span className="text-xs text-text-tertiary">&mdash;</span>;
    }
    if (value < 0) {
        return <span className="text-xs" style={{ color: '#10B981' }}>↓{Math.abs(value)}%</span>;
    }
    return <span className="text-xs" style={{ color: '#FB7185' }}>↑{value}%</span>;
}

export default function CategoryCard({ id, name, color, amountCents, transactionCount, trendPercentage, currentMonth, currentAccount }: Props) {
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
            <div className="ml-auto text-right">
                <p
                    className="text-2xl font-semibold tabular-nums"
                    style={{ color }}
                >
                    {formatAbsAmount(amountCents)}
                </p>
                {trendPercentage !== undefined && <TrendIndicator value={trendPercentage} />}
            </div>
        </Link>
    );
}
