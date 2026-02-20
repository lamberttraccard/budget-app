import { formatAmount } from '@/lib/formatAmount';

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
                <p className="text-lg font-semibold tabular-nums text-[#10B981]">
                    {showDashes ? '\u2014' : formatAmount(incomeCents)}
                </p>
            </div>
            <div className="flex-1 bg-surface rounded-xl p-4">
                <p className="text-xs text-text-secondary mb-1">Expenses</p>
                <p className="text-lg font-semibold tabular-nums text-[#FB7185]">
                    {showDashes ? '\u2014' : formatAmount(expensesCents)}
                </p>
            </div>
        </div>
    );
}
