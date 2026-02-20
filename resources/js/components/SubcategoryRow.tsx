import { ChevronRightIcon } from 'lucide-react';
import { formatAbsAmount } from '@/lib/formatAmount';

type Props = {
    name: string;
    amountCents: number;
    transactionCount: number;
    isActive: boolean;
    onClick: () => void;
};

export default function SubcategoryRow({ name, amountCents, transactionCount, isActive, onClick }: Props) {
    return (
        <button
            type="button"
            onClick={onClick}
            className={`flex w-full items-center gap-3 px-4 py-3 text-left transition-colors hover:bg-surface-elevated ${isActive ? 'bg-surface-elevated' : ''}`}
        >
            <div className="flex-1 min-w-0">
                <p className="text-[15px] font-medium text-white truncate">{name}</p>
                <p className="text-xs text-text-secondary">
                    {transactionCount} transaction{transactionCount !== 1 ? 's' : ''}
                </p>
            </div>
            <p className="text-base font-semibold tabular-nums text-white ml-auto">
                {formatAbsAmount(amountCents)}
            </p>
            <ChevronRightIcon className={`size-4 flex-shrink-0 transition-transform ${isActive ? 'rotate-90 text-white' : 'text-text-tertiary'}`} />
        </button>
    );
}
