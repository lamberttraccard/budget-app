import { AnimatedNumber } from '@/components/AnimatedNumber';
import MiniSparkline from '@/components/MiniSparkline';
import { formatAbsAmount } from '@/lib/formatAmount';

import type { CategoryTotal } from '@/types';

type Props = {
    incomeCents: number;
    expensesCents: number;
    avgExpensesCents?: number;
    showDashes?: boolean;
    /** Spending categories, drawn as the split bar. */
    spending: CategoryTotal[];
    /** Monthly expense totals, drawn as a faint trend behind the split bar. */
    expenseTrend: number[];
};

function formatSigned(cents: number): string {
    const sign = cents > 0 ? '+' : cents < 0 ? '\u2212' : '';
    return `${sign}${formatAbsAmount(cents)}`;
}

function Chip({
    label,
    cents,
    color,
    showDashes,
}: {
    label: string;
    cents: number;
    color: string;
    showDashes: boolean;
}) {
    return (
        <span className="inline-flex h-7 items-center gap-2 rounded-full glass px-3 text-xs text-white/85">
            <span
                className="size-1.5 rounded-full"
                style={{
                    backgroundColor: color,
                    boxShadow: `0 0 8px ${color}`,
                }}
            />
            {label}{' '}
            <span className="tabular-nums">
                {showDashes ? '\u2014' : formatAbsAmount(cents)}
            </span>
        </span>
    );
}

function AverageComparison({
    expensesCents,
    avgExpensesCents,
}: {
    expensesCents: number;
    avgExpensesCents: number;
}) {
    const diff = avgExpensesCents - expensesCents;
    if (diff === 0) return <span className="text-white/60">On average</span>;

    return diff > 0 ? (
        <span className="text-emerald-300 tabular-nums">
            {formatAbsAmount(diff)} under average
        </span>
    ) : (
        <span className="text-rose-300 tabular-nums">
            {formatAbsAmount(diff)} over average
        </span>
    );
}

export default function SummaryWidget({
    incomeCents,
    expensesCents,
    avgExpensesCents,
    showDashes = false,
    spending,
    expenseTrend,
}: Props) {
    return (
        <div className="space-y-4">
            <div>
                <p className="text-sm text-white/60">Net this month</p>
                {showDashes ? (
                    <p className="mt-1.5 text-5xl font-light">{'\u2014'}</p>
                ) : (
                    <AnimatedNumber
                        value={incomeCents - expensesCents}
                        formatter={formatSigned}
                        className="mt-1.5 block text-5xl font-light tracking-[-0.04em] tabular-nums"
                        decimalsClassName="ml-0.5 text-[0.45em] tracking-normal text-white/50"
                    />
                )}
                <div className="mt-3 flex flex-wrap gap-1.5">
                    <Chip
                        label="In"
                        cents={incomeCents}
                        color="#34D399"
                        showDashes={showDashes}
                    />
                    <Chip
                        label="Out"
                        cents={expensesCents}
                        color="#FB7185"
                        showDashes={showDashes}
                    />
                </div>
            </div>

            {!showDashes && spending.length > 0 && (
                <div className="relative overflow-hidden rounded-panel glass px-4 pt-3 pb-6">
                    <div className="flex items-center justify-between gap-3 text-xs">
                        <span className="text-white/60">Spent</span>
                        {avgExpensesCents !== undefined && (
                            <AverageComparison
                                expensesCents={expensesCents}
                                avgExpensesCents={avgExpensesCents}
                            />
                        )}
                    </div>
                    <div
                        className="mt-2.5 flex h-2 gap-[3px]"
                        role="img"
                        aria-label="Spending split by category"
                    >
                        {spending.map((cat) => (
                            <span
                                key={cat.id}
                                title={cat.name}
                                className="min-w-1 rounded-full"
                                style={{
                                    flexGrow: Math.abs(cat.amountCents),
                                    backgroundColor: cat.color,
                                    boxShadow: `0 0 10px ${cat.color}66`,
                                }}
                            />
                        ))}
                    </div>
                    <MiniSparkline values={expenseTrend} color="#FB7185" />
                </div>
            )}
        </div>
    );
}
