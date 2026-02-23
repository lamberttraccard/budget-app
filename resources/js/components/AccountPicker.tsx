import { ChevronDownIcon } from 'lucide-react';
import { useCallback, useMemo } from 'react';
import { Checkbox } from '@/components/ui/checkbox';
import { Popover, PopoverContent, PopoverTrigger } from '@/components/ui/popover';
import type { AccountContext } from '@/types';

type Props = {
    selectedAccountIds: number[];
    accountContexts: AccountContext[];
    onAccountsChange: (accountIds: number[]) => void;
    dark?: boolean;
};

export default function AccountPicker({ selectedAccountIds, accountContexts, onAccountsChange, dark }: Props) {
    const textClass = dark ? 'text-black/70' : 'text-text-secondary';
    const hoverClass = dark ? 'hover:text-black/90' : 'hover:text-white';

    const allIds = useMemo(() => accountContexts.map(a => a.id), [accountContexts]);

    // Empty array = "all accounts"
    const isAllSelected = selectedAccountIds.length === 0 || selectedAccountIds.length === allIds.length;

    const effectiveIds = isAllSelected ? allIds : selectedAccountIds;

    const label = useMemo(() => {
        if (isAllSelected) return 'All accounts';
        if (selectedAccountIds.length === 1) {
            return accountContexts.find(a => a.id === selectedAccountIds[0])?.name ?? 'All accounts';
        }
        return `${selectedAccountIds.length} accounts`;
    }, [isAllSelected, selectedAccountIds, accountContexts]);

    const persistAndChange = useCallback((ids: number[]) => {
        document.cookie = `selectedAccounts=${ids.join(',')};path=/;max-age=31536000;SameSite=Lax`;
        onAccountsChange(ids);
    }, [onAccountsChange]);

    const toggleAccount = useCallback((id: number) => {
        if (isAllSelected) {
            // Uncheck this one from "all"
            persistAndChange(allIds.filter(i => i !== id));
        } else if (effectiveIds.includes(id)) {
            const next = effectiveIds.filter(i => i !== id);
            // If none left, go back to "all"
            persistAndChange(next.length === 0 ? [] : next);
        } else {
            const next = [...effectiveIds, id];
            // If all selected now, store as empty = "all"
            persistAndChange(next.length === allIds.length ? [] : next);
        }
    }, [isAllSelected, effectiveIds, allIds, persistAndChange]);

    if (accountContexts.length <= 1) {
        return (
            <span className={`text-sm font-medium ${textClass}`}>
                {accountContexts[0]?.name ?? 'All accounts'}
            </span>
        );
    }

    return (
        <Popover>
            <PopoverTrigger className={`flex items-center gap-1 text-sm font-medium ${textClass} ${hoverClass} transition-colors outline-none`}>
                {label}
                <ChevronDownIcon className="size-4" />
            </PopoverTrigger>
            <PopoverContent align="end" className="w-56 p-2">
                {accountContexts.map(account => (
                    <label
                        key={account.id}
                        className="flex items-center gap-2 rounded px-2 py-1.5 hover:bg-accent cursor-pointer"
                    >
                        <Checkbox
                            checked={effectiveIds.includes(account.id)}
                            onCheckedChange={() => toggleAccount(account.id)}
                        />
                        <span className="text-sm">{account.name}</span>
                    </label>
                ))}
            </PopoverContent>
        </Popover>
    );
}
