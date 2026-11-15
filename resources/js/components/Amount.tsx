import { formatAbsAmount, splitDecimals } from '@/lib/formatAmount';

type Props = {
    cents: number;
    className?: string;
};

/** Absolute amount with its decimals and currency dimmed, in the Aurora style. */
export default function Amount({ cents, className }: Props) {
    const [whole, decimals] = splitDecimals(formatAbsAmount(cents));

    return (
        <span className={`tabular-nums ${className ?? ''}`}>
            {whole}
            <span className="text-[0.8em] text-white/45">{decimals}</span>
        </span>
    );
}
