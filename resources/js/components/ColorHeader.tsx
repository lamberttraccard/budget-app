import { ArrowLeftIcon } from 'lucide-react';
import { AnimatedNumber } from '@/components/AnimatedNumber';
import { CategoryIcon } from '@/lib/category-icons';

type Props = {
    name: string;
    color: string;
    icon: string | null;
    totalAmountCents: number;
    transactionCount: number;
    onBack: () => void;
};

/** Returns true if the hex color is light enough for dark text. */
function isLightColor(hex: string): boolean {
    const r = parseInt(hex.slice(1, 3), 16) / 255;
    const g = parseInt(hex.slice(3, 5), 16) / 255;
    const b = parseInt(hex.slice(5, 7), 16) / 255;
    // Relative luminance (WCAG formula)
    const luminance =
        0.2126 * (r <= 0.03928 ? r / 12.92 : ((r + 0.055) / 1.055) ** 2.4) +
        0.7152 * (g <= 0.03928 ? g / 12.92 : ((g + 0.055) / 1.055) ** 2.4) +
        0.0722 * (b <= 0.03928 ? b / 12.92 : ((b + 0.055) / 1.055) ** 2.4);
    return luminance > 0.35;
}

export default function ColorHeader({ name, color, icon, totalAmountCents, transactionCount, onBack }: Props) {
    const light = isLightColor(color);
    const textPrimary = light ? 'text-black/90' : 'text-white/90';
    const textSecondary = light ? 'text-black/60' : 'text-white/60';
    const textBack = light ? 'text-black/70 hover:text-black' : 'text-white/70 hover:text-white';

    return (
        <div className="relative -mx-4 sm:-mx-8 px-4 sm:px-8 pt-4 pb-8" style={{ backgroundColor: color }}>
            <button
                type="button"
                onClick={onBack}
                className={`absolute top-4 left-4 sm:left-8 flex items-center gap-1 text-sm font-medium ${textBack}`}
            >
                <ArrowLeftIcon className="size-5" />
                Back
            </button>

            <div className="flex flex-col items-center pt-8">
                <div
                    className="w-16 h-16 rounded-2xl mb-4 flex items-center justify-center"
                    style={{ backgroundColor: 'rgba(0,0,0,0.15)' }}
                    aria-hidden="true"
                >
                    <CategoryIcon name={icon} className={`size-8 ${textSecondary}`} />
                </div>

                <h1 className={`text-xl font-semibold ${textPrimary}`}>{name}</h1>

                <AnimatedNumber
                    value={totalAmountCents}
                    className={`mt-2 text-4xl font-bold tabular-nums ${textPrimary}`}
                />

                <p className={`mt-1 text-sm ${textSecondary}`}>
                    {transactionCount} transaction{transactionCount !== 1 ? 's' : ''}
                </p>
            </div>
        </div>
    );
}
