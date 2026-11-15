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
    /** Secondary line under the name, such as the share of spending. */
    subtitle: string;
    avgAmountCents?: number;
    currentMonth: string;
    selectedAccountIds: number[];
};

export default function CategoryRow({ id, name, color, icon, amountCents, subtitle, avgAmountCents, currentMonth, selectedAccountIds }: Props) {
    let deltaNode = null;
    if (avgAmountCents !== undefined) {
        const deltaCents = amountCents - avgAmountCents;
        if (deltaCents !== 0) {
            const isExpense = amountCents < 0;
            // Arrow: for expenses, invert direction (positive delta = spending ↓)
            const arrow = isExpense
                ? (deltaCents > 0 ? '\u2193' : '\u2191')
                : (deltaCents > 0 ? '\u2191' : '\u2193');

            deltaNode = (
                <p className="text-xs text-white/40 tabular-nums" title="Compared with your monthly average">
                    {arrow}&thinsp;{formatAbsAmount(deltaCents)}
                </p>
            );
        }
    }

    return (
        <Link
            href={`/categories/${id}?month=${currentMonth}${selectedAccountIds.length > 0 ? `&accounts=${selectedAccountIds.join(',')}` : ''}`}
            className="-mx-2 flex items-center gap-3 rounded-2xl px-2 py-3 transition-colors hover:bg-white/[0.04]"
        >
            <span
                className="flex size-9 flex-shrink-0 items-center justify-center rounded-full"
                style={{
                    backgroundColor: `${color}22`,
                    boxShadow: `inset 0 0 0 1px ${color}55, 0 0 14px ${color}33`,
                    color,
                }}
                aria-hidden="true"
            >
                <CategoryIcon name={icon} className="size-4" />
            </span>
            <div className="min-w-0 flex-1">
                <p className="truncate text-[15px] text-white">{name}</p>
                <p className="truncate text-xs text-white/50">{subtitle}</p>
            </div>
            <div className="ml-auto flex flex-col items-end">
                <AnimatedNumber
                    value={amountCents}
                    className="text-base font-medium tabular-nums text-white"
                    decimalsClassName="text-[0.8em] font-normal text-white/45"
                />
                {deltaNode}
            </div>
        </Link>
    );
}
