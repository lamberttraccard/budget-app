import { Link } from '@inertiajs/react';
import { AnimatedNumber } from '@/components/AnimatedNumber';
import { CategoryIcon } from '@/lib/category-icons';
import { formatAbsAmount } from '@/lib/formatAmount';

type Props = {
    id: number;
    name: string;
    color: string;
    icon: string | null;
    amountCents: number;
    transactionCount: number;
    avgAmountCents?: number;
    currentMonth: string;
    currentAccount: string;
};

export default function CategoryCard({ id, name, color, icon, amountCents, transactionCount, avgAmountCents, currentMonth, currentAccount }: Props) {
    const showAvg = avgAmountCents !== undefined;

    let deltaNode = null;
    if (showAvg) {
        const deltaCents = amountCents - avgAmountCents;
        if (deltaCents !== 0) {
            const isExpense = amountCents < 0;
            // Color: positive delta is favorable for both (less expense / more income)
            const isFavorable = deltaCents > 0;
            const deltaColor = isFavorable ? '#10B981' : '#FB7185';
            // Arrow: for expenses, invert direction (positive delta = spending ↓)
            const arrow = isExpense
                ? (deltaCents > 0 ? '\u2193' : '\u2191')
                : (deltaCents > 0 ? '\u2191' : '\u2193');

            deltaNode = (
                <span className="tabular-nums" style={{ color: deltaColor }}>
                    {arrow}&thinsp;{formatAbsAmount(deltaCents)}
                </span>
            );
        }
    }

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
                {showAvg && (
                    <p className="text-xs text-text-tertiary">
                        Avg: {formatAbsAmount(avgAmountCents)} {deltaNode}
                    </p>
                )}
            </div>
        </Link>
    );
}
