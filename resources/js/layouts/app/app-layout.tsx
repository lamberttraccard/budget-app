import { Link, router, usePage } from '@inertiajs/react';
import { ChevronLeft } from 'lucide-react';
import { useCallback } from 'react';
import { Toaster } from 'sonner';
import AccountPicker from '@/components/AccountPicker';
import AppLogoIcon from '@/components/app-logo-icon';
import BottomDock from '@/components/BottomDock';
import type { AppLayoutProps, SharedProps } from '@/types';

/** Aurora glow behind the top of the page: the category color on category pages, teal and indigo elsewhere. */
function auroraBackground(accentColor?: string): string {
    if (accentColor) {
        return `radial-gradient(85% 60% at 50% -10%, ${accentColor}6B, transparent 72%)`;
    }
    return 'radial-gradient(70% 55% at 10% -8%, rgba(45,212,191,0.30), transparent 70%), radial-gradient(60% 50% at 100% 0%, rgba(129,140,248,0.28), transparent 70%)';
}

export default function AppLayout({ children, onAccountsChange, accentColor, backHref, headerCenter }: AppLayoutProps) {
    const { accountContexts, selectedAccountIds: sharedAccountIds } = usePage<SharedProps>().props;

    const selectedAccountIds = sharedAccountIds ?? [];

    const defaultAccountsChange = useCallback((accountIds: number[]) => {
        const params: Record<string, string> = {};
        if (accountIds.length > 0) params.accounts = accountIds.join(',');
        router.get(window.location.pathname, params, {
            preserveState: true,
            preserveScroll: true,
        });
    }, []);

    const handleAccountsChange = onAccountsChange ?? defaultAccountsChange;

    return (
        <div className="relative min-h-screen bg-background overflow-x-hidden">
            <div
                className="pointer-events-none absolute inset-x-0 top-0 h-[560px]"
                style={{ background: auroraBackground(accentColor) }}
                aria-hidden="true"
            />

            <header className="relative z-20 px-4 sm:px-8 py-4 pt-[calc(env(safe-area-inset-top)+1rem)]">
                <div className="max-w-[480px] lg:max-w-[720px] mx-auto grid grid-cols-[1fr_auto_1fr] items-center gap-3">
                    <div className="justify-self-start">
                        {backHref ? (
                            <Link
                                href={backHref}
                                aria-label="Back to dashboard"
                                className="glass flex size-9 items-center justify-center rounded-full text-white transition-colors hover:bg-white/10"
                            >
                                <ChevronLeft className="size-4" />
                            </Link>
                        ) : (
                            <Link href="/" aria-label="Go to dashboard">
                                <AppLogoIcon className="size-9 rounded-full" />
                            </Link>
                        )}
                    </div>
                    <div className="min-w-0">{headerCenter}</div>
                    <div className="justify-self-end">
                        {accountContexts?.length > 1 && (
                            <AccountPicker
                                selectedAccountIds={selectedAccountIds}
                                accountContexts={accountContexts}
                                onAccountsChange={handleAccountsChange}
                            />
                        )}
                    </div>
                </div>
            </header>

            <main className="relative z-10 px-4 sm:px-8 pb-32">
                <div className="max-w-[480px] lg:max-w-[720px] mx-auto">
                    {children}
                </div>
            </main>

            <Toaster
                theme="dark"
                position="top-center"
                duration={2000}
            />

            <BottomDock />
        </div>
    );
}
