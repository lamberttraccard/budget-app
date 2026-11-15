import { AnimatedNumber } from '@/components/AnimatedNumber';
import { CategoryIcon } from '@/lib/category-icons';

type Props = {
    name: string;
    color: string;
    icon: string | null;
    totalAmountCents: number;
    transactionCount: number;
};

export default function CategoryHero({ name, color, icon, totalAmountCents, transactionCount }: Props) {
    return (
        <div className="pt-2">
            <div className="flex items-center gap-3">
                <span
                    className="flex size-10 items-center justify-center rounded-full"
                    style={{
                        backgroundColor: `${color}26`,
                        boxShadow: `inset 0 0 0 1px ${color}66, 0 0 18px ${color}55`,
                        color,
                    }}
                    aria-hidden="true"
                >
                    <CategoryIcon name={icon} className="size-5" />
                </span>
                <div>
                    <h1 className="text-base text-white">{name}</h1>
                    <p className="text-xs text-white/55">
                        {transactionCount} transaction{transactionCount !== 1 ? 's' : ''}
                    </p>
                </div>
            </div>

            <AnimatedNumber
                value={totalAmountCents}
                className="mt-4 block text-5xl font-light tracking-[-0.04em] tabular-nums text-white"
                decimalsClassName="ml-0.5 text-[0.45em] tracking-normal text-white/50"
            />
        </div>
    );
}
