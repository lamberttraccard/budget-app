import { usePage } from '@inertiajs/react';
import AccountPicker from '@/components/AccountPicker';
import type { AccountContext, AppLayoutProps } from '@/types';

export default function AppLayout({ children, currentAccount, onAccountChange }: AppLayoutProps) {
    const { accountContexts } = usePage<{ props: { accountContexts: AccountContext[] } }>().props as unknown as { accountContexts: AccountContext[] };

    return (
        <div className="min-h-screen bg-background">
            <header className="px-4 sm:px-8 py-4">
                <div className="max-w-[480px] mx-auto flex items-center justify-between">
                    <span className="text-lg font-semibold text-white">
                        budget-app
                    </span>
                    {accountContexts?.length > 0 && currentAccount && onAccountChange && (
                        <AccountPicker
                            currentAccount={currentAccount}
                            accountContexts={accountContexts}
                            onAccountChange={onAccountChange}
                        />
                    )}
                </div>
            </header>

            <main className="px-4 sm:px-8">
                <div className="max-w-[480px] mx-auto">
                    {children}
                </div>
            </main>
        </div>
    );
}
