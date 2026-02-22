import { Link } from '@inertiajs/react';
import { AnimatedNumber } from '@/components/AnimatedNumber';
import { CategoryIcon } from '@/lib/category-icons';

type Props = {
    id: number;
    name: string;
    color: string;
    icon: string | null;
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

export default function CategoryCard({ id, name, color, icon, amountCents, transactionCount, trendPercentage, currentMonth, currentAccount }: Props) {
    return (
        <Link
            href={`/categories/${id}?month=${currentMonth}&account=${currentAccount}`}
            className="flex items-center gap-4 rounded-xl p-4 bg-surface/80 backdrop-blur-2xl border border-white/[0.06] transition-colors hover:bg-surface-elevated/80"
        >
            <div
                className="w-12 h-12 rounded-xl flex-shrink-0 flex items-center justify-center"
                style={{ backgroundColor: color }}
                aria-hidden="true"
            >
                <CategoryIcon name={icon} className="size-6 text-black/60" />
            </div>
            <div className="flex-1 min-w-0">
                <p className="text-sm font-normal text-white truncate">{name}</p>
                <p className="text-xs text-text-secondary">
                    {transactionCount} transaction{transactionCount !== 1 ? 's' : ''}
                </p>
            </div>
            <div className="ml-auto flex flex-col items-end">
                <AnimatedNumber
                    value={amountCents}
                    className="text-2xl font-semibold tabular-nums"
                    style={{ color }}
                />
                {trendPercentage !== undefined && <TrendIndicator value={trendPercentage} />}
            </div>
        </Link>
    );
}
