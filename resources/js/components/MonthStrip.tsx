import { useEffect, useRef } from 'react';

type Props = {
    currentMonth: string;
    availableMonths: string[];
    onMonthChange: (month: string) => void;
};

function formatMonthPill(month: string): string {
    const [year, m] = month.split('-');
    const date = new Date(Number(year), Number(m) - 1);
    return date.toLocaleDateString('en-US', { month: 'short', year: '2-digit' });
}

function generateMonthRange(availableMonths: string[], currentMonth: string): string[] {
    if (availableMonths.length === 0) return [currentMonth];

    const earliest = currentMonth < availableMonths[0] ? currentMonth : availableMonths[0];
    const latest = currentMonth > availableMonths[availableMonths.length - 1]
        ? currentMonth
        : availableMonths[availableMonths.length - 1];

    const months: string[] = [];
    const [startYear, startMonth] = earliest.split('-').map(Number);
    const [endYear, endMonth] = latest.split('-').map(Number);

    let y = startYear;
    let m = startMonth;

    while (y < endYear || (y === endYear && m <= endMonth)) {
        months.push(`${y}-${String(m).padStart(2, '0')}`);
        m++;
        if (m > 12) {
            m = 1;
            y++;
        }
    }

    return months;
}

export default function MonthStrip({ currentMonth, availableMonths, onMonthChange }: Props) {
    const activeRef = useRef<HTMLButtonElement>(null);

    useEffect(() => {
        if (activeRef.current) {
            activeRef.current.scrollIntoView({
                inline: 'center',
                behavior: 'instant',
            });
        }
    }, []);

    const months = generateMonthRange(availableMonths, currentMonth);

    return (
        <div className="overflow-x-auto scrollbar-none -mx-4 sm:-mx-8 px-4 sm:px-8">
            <div className="flex gap-2 snap-x snap-mandatory">
                {months.map((month) => (
                    <button
                        key={month}
                        ref={month === currentMonth ? activeRef : undefined}
                        type="button"
                        onClick={() => onMonthChange(month)}
                        className={`flex-none snap-center rounded-full px-3 py-3 text-sm whitespace-nowrap transition-colors ${
                            month === currentMonth
                                ? 'bg-surface-elevated text-white font-medium'
                                : 'text-text-secondary hover:text-white'
                        }`}
                    >
                        {formatMonthPill(month)}
                    </button>
                ))}
            </div>
        </div>
    );
}
