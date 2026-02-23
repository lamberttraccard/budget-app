import { AnimatedNumber } from '@/components/AnimatedNumber';
import MiniSparkline from '@/components/MiniSparkline';
import { formatAmount, formatAbsAmount } from '@/lib/formatAmount';

import type { SparklineData } from '@/types';

type Props = {
    incomeCents: number;
    expensesCents: number;
    avgIncomeCents?: number;
    avgExpensesCents?: number;
    showDashes?: boolean;
    sparklineData?: SparklineData;
};

function DeltaIndicator({ deltaCents }: { deltaCents: number }) {
    if (deltaCents === 0) return null;

    const arrow = deltaCents > 0 ? '\u2191' : '\u2193';

    return (
        <p className="text-xs text-text-tertiary tabular-nums mt-0.5">
            {arrow}&thinsp;{formatAbsAmount(deltaCents)}
        </p>
    );
}

export default function SummaryWidget({ incomeCents, expensesCents, avgIncomeCents, avgExpensesCents, showDashes = false, sparklineData }: Props) {
    const incomeAvgLine = avgIncomeCents !== undefined;
    const expenseAvgLine = avgExpensesCents !== undefined;
    const incomeValues = sparklineData?.monthlyIncomeTotals.map((d) => d.amountCents) ?? [];
    const expenseValues = sparklineData?.monthlyExpenseTotals.map((d) => d.amountCents) ?? [];

    return (
        <div className="flex gap-3">
            <div className="relative flex-1 overflow-hidden rounded-xl p-4 bg-surface/60 backdrop-blur-2xl border border-white/[0.06]">
                <p className="text-xs text-text-secondary mb-1">Income</p>
                {showDashes ? (
                    <p className="text-lg font-semibold tabular-nums text-[#10B981]">{'\u2014'}</p>
                ) : (
                    <AnimatedNumber
                        value={incomeCents}
                        formatter={formatAmount}
                        className="text-lg font-semibold tabular-nums text-[#10B981]"
                    />
                )}
                {incomeAvgLine && !showDashes && (
                    <DeltaIndicator deltaCents={incomeCents - avgIncomeCents} />
                )}
                <MiniSparkline values={incomeValues} color="#10B981" />
            </div>
            <div className="relative flex-1 overflow-hidden rounded-xl p-4 bg-surface/60 backdrop-blur-2xl border border-white/[0.06]">
                <p className="text-xs text-text-secondary mb-1">Expenses</p>
                {showDashes ? (
                    <p className="text-lg font-semibold tabular-nums text-[#FB7185]">{'\u2014'}</p>
                ) : (
                    <AnimatedNumber
                        value={expensesCents}
                        formatter={formatAbsAmount}
                        className="text-lg font-semibold tabular-nums text-[#FB7185]"
                    />
                )}
                {expenseAvgLine && !showDashes && (
                    <DeltaIndicator deltaCents={expensesCents - avgExpensesCents} />
                )}
                <MiniSparkline values={expenseValues} color="#FB7185" />
            </div>
        </div>
    );
}
