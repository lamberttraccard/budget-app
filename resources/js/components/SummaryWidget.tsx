import { AnimatedNumber } from '@/components/AnimatedNumber';
import { formatAmount, formatAbsAmount } from '@/lib/formatAmount';

type Props = {
    incomeCents: number;
    expensesCents: number;
    showDashes?: boolean;
};

export default function SummaryWidget({ incomeCents, expensesCents, showDashes = false }: Props) {
    return (
        <div className="flex gap-3">
            <div className="flex-1 bg-surface rounded-xl p-4">
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
            </div>
            <div className="flex-1 bg-surface rounded-xl p-4">
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
            </div>
        </div>
    );
}
