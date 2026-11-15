import { ChevronDownIcon } from 'lucide-react';
import { useState } from 'react';
import { Popover, PopoverContent, PopoverTrigger } from '@/components/ui/popover';

type Props = {
    currentMonth: string;
    availableMonths: string[];
    onMonthChange: (month: string) => void;
};

function toDate(month: string): Date {
    const [year, m] = month.split('-');
    return new Date(Number(year), Number(m) - 1);
}

function todayMonth(): string {
    const now = new Date();
    return `${now.getFullYear()}-${String(now.getMonth() + 1).padStart(2, '0')}`;
}

/** Every month from the earliest to the latest of the data range, the current month, and today. */
function generateMonthRange(availableMonths: string[], currentMonth: string): string[] {
    if (availableMonths.length === 0) return [currentMonth];

    const today = todayMonth();
    const candidates = [currentMonth, today, availableMonths[0], availableMonths[availableMonths.length - 1]];
    const earliest = candidates.reduce((a, b) => (a < b ? a : b));
    const latest = candidates.reduce((a, b) => (a > b ? a : b));

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

export default function MonthPicker({ currentMonth, availableMonths, onMonthChange }: Props) {
    const [open, setOpen] = useState(false);

    const months = generateMonthRange(availableMonths, currentMonth);
    const years = [...new Set(months.map((month) => month.slice(0, 4)))].reverse();
    const withData = new Set(availableMonths);

    const select = (month: string) => {
        setOpen(false);
        if (month !== currentMonth) onMonthChange(month);
    };

    return (
        <Popover open={open} onOpenChange={setOpen}>
            <PopoverTrigger className="glass mx-auto flex h-9 items-center gap-1.5 rounded-full px-4 text-sm text-white whitespace-nowrap transition-colors hover:bg-white/10 outline-none focus-visible:ring-2 focus-visible:ring-white/50">
                {toDate(currentMonth).toLocaleDateString('en-US', { month: 'long', year: 'numeric' })}
                <ChevronDownIcon className="size-4 text-white/60" />
            </PopoverTrigger>
            <PopoverContent align="center" className="w-72 max-h-80 overflow-y-auto rounded-2xl p-3">
                <div className="space-y-3">
                    {years.map((year) => (
                        <div key={year}>
                            <p className="px-1 pb-1.5 text-xs text-text-secondary">{year}</p>
                            <div className="grid grid-cols-4 gap-1">
                                {months
                                    .filter((month) => month.startsWith(year))
                                    .map((month) => (
                                        <button
                                            key={month}
                                            type="button"
                                            onClick={() => select(month)}
                                            aria-current={month === currentMonth ? 'date' : undefined}
                                            className={`h-9 rounded-full text-sm transition-colors ${
                                                month === currentMonth
                                                    ? 'bg-foreground text-background font-medium'
                                                    : withData.has(month)
                                                        ? 'text-white hover:bg-white/10'
                                                        : 'text-white/35 hover:bg-white/10 hover:text-white'
                                            }`}
                                        >
                                            {toDate(month).toLocaleDateString('en-US', { month: 'short' })}
                                        </button>
                                    ))}
                            </div>
                        </div>
                    ))}
                </div>
            </PopoverContent>
        </Popover>
    );
}
