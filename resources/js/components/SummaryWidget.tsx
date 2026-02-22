import { AnimatedNumber } from '@/components/AnimatedNumber';
import { formatAmount, formatAbsAmount } from '@/lib/formatAmount';

type Props = {
    incomeCents: number;
    expensesCents: number;
    avgIncomeCents?: number;
    avgExpensesCents?: number;
    showDashes?: boolean;
};

function DeltaIndicator({ deltaCents, favorableWhenPositive }: { deltaCents: number; favorableWhenPositive: boolean }) {
    if (deltaCents === 0) return null;

    const isFavorable = favorableWhenPositive ? deltaCents > 0 : deltaCents < 0;
    const arrow = deltaCents > 0 ? '\u2191' : '\u2193';
    const color = isFavorable ? '#10B981' : '#FB7185';

    return (
        <span className="tabular-nums" style={{ color }}>
            {arrow}&thinsp;{formatAbsAmount(deltaCents)}
        </span>
    );
}

export default function SummaryWidget({ incomeCents, expensesCents, avgIncomeCents, avgExpensesCents, showDashes = false }: Props) {
    const incomeAvgLine = avgIncomeCents !== undefined;
    const expenseAvgLine = avgExpensesCents !== undefined;

    return (
        <div className="flex gap-3">
            <div className="flex-1 rounded-xl p-4 bg-surface/60 backdrop-blur-2xl border border-white/[0.06]">
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
                    <p className="text-xs text-text-tertiary mt-1">
                        Avg: {formatAmount(avgIncomeCents)}{' '}
                        <DeltaIndicator deltaCents={incomeCents - avgIncomeCents} favorableWhenPositive={true} />
                    </p>
                )}
            </div>
            <div className="flex-1 rounded-xl p-4 bg-surface/60 backdrop-blur-2xl border border-white/[0.06]">
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
                    <p className="text-xs text-text-tertiary mt-1">
                        Avg: {formatAbsAmount(avgExpensesCents)}{' '}
                        <DeltaIndicator deltaCents={expensesCents - avgExpensesCents} favorableWhenPositive={false} />
                    </p>
                )}
            </div>
        </div>
    );
}
