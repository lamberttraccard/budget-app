import { ChevronRightIcon } from 'lucide-react';
import Amount from '@/components/Amount';

type Props = {
    name: string;
    amountCents: number;
    transactionCount: number;
    isActive: boolean;
    onClick: () => void;
};

export default function SubcategoryRow({
    name,
    amountCents,
    transactionCount,
    isActive,
    onClick,
}: Props) {
    return (
        <button
            type="button"
            onClick={onClick}
            className={`flex w-full items-center gap-3 px-4 py-3 text-left transition-colors hover:bg-white/[0.04] ${isActive ? 'bg-white/[0.06]' : ''}`}
        >
            <div className="min-w-0 flex-1">
                <p className="truncate text-[15px] text-white">{name}</p>
                <p className="text-xs text-white/50">
                    {transactionCount} transaction
                    {transactionCount !== 1 ? 's' : ''}
                </p>
            </div>
            <Amount
                cents={amountCents}
                className="ml-auto text-base font-medium text-white"
            />
            <ChevronRightIcon
                className={`size-4 flex-shrink-0 transition-transform ${isActive ? 'rotate-90 text-white' : 'text-white/40'}`}
            />
        </button>
    );
}
