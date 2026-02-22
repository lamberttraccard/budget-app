import { ChevronDownIcon, CheckIcon } from 'lucide-react';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import type { AccountContext } from '@/types';

type Props = {
    currentAccount: string;
    accountContexts: AccountContext[];
    onAccountChange: (account: string) => void;
    dark?: boolean;
};

export default function AccountPicker({ currentAccount, accountContexts, onAccountChange, dark }: Props) {
    const currentLabel = accountContexts.find(c => c.value === currentAccount)?.label ?? 'Personal';
    const textClass = dark ? 'text-black/70' : 'text-text-secondary';
    const hoverClass = dark ? 'hover:text-black/90' : 'hover:text-white';

    if (accountContexts.length <= 1) {
        return (
            <span className={`text-sm font-medium ${textClass}`}>
                {currentLabel}
            </span>
        );
    }

    return (
        <DropdownMenu>
            <DropdownMenuTrigger className={`flex items-center gap-1 text-sm font-medium ${textClass} ${hoverClass} transition-colors outline-none`}>
                {currentLabel}
                <ChevronDownIcon className="size-4" />
            </DropdownMenuTrigger>
            <DropdownMenuContent align="end">
                {accountContexts.map((context) => (
                    <DropdownMenuItem
                        key={context.value}
                        onClick={() => { if (context.value !== currentAccount) onAccountChange(context.value); }}
                        className="flex items-center justify-between gap-4"
                    >
                        {context.label}
                        {context.value === currentAccount && (
                            <CheckIcon className="size-4 text-white" />
                        )}
                    </DropdownMenuItem>
                ))}
            </DropdownMenuContent>
        </DropdownMenu>
    );
}
