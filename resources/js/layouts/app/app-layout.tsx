import { Link, router, usePage } from '@inertiajs/react';
import { useCallback } from 'react';
import { Toaster } from 'sonner';
import AccountPicker from '@/components/AccountPicker';
import AppLogoIcon from '@/components/app-logo-icon';
import BottomDock from '@/components/BottomDock';
import { useNavItems } from '@/hooks/use-nav-items';
import type { AppLayoutProps, SharedProps } from '@/types';

export default function AppLayout({ children, onAccountsChange, accentColor }: AppLayoutProps) {
    const { accountContexts, selectedAccountIds: sharedAccountIds } = usePage<SharedProps>().props;

    const selectedAccountIds = sharedAccountIds ?? [];
    const navItems = useNavItems(selectedAccountIds);

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
        <div className="min-h-screen bg-background overflow-x-hidden">
            <header className="relative z-20 px-4 sm:px-8 py-4 pt-[calc(env(safe-area-inset-top)+1rem)]">
                <div className="max-w-[480px] lg:max-w-[960px] mx-auto flex items-center justify-between">
                    <div className="flex items-center gap-4">
                        <Link href="/" aria-label="Go to dashboard">
                            <AppLogoIcon className="size-8 rounded-lg" />
                        </Link>
                        <nav className="hidden lg:flex items-center gap-1" aria-label="Main navigation">
                            {navItems.map((item) => (
                                <Link
                                    key={item.label}
                                    href={item.href}
                                    aria-current={item.isActive ? 'page' : undefined}
                                    className={`px-3 py-1.5 rounded-lg text-sm font-medium transition-colors focus-visible:ring-2 focus-visible:ring-white/50 focus-visible:outline-none backdrop-blur-2xl ${
                                        item.isActive
                                            ? accentColor
                                                ? 'bg-black/65'
                                                : 'bg-surface/80'
                                            : accentColor
                                                ? 'text-black/70 hover:text-black/90'
                                                : 'text-text-tertiary hover:text-white'
                                    }`}
                                    style={item.isActive && accentColor ? { color: accentColor } : undefined}
                                >
                                    {item.label}
                                </Link>
                            ))}
                        </nav>
                    </div>
                    {accountContexts?.length > 1 && (
                        <AccountPicker
                            selectedAccountIds={selectedAccountIds}
                            accountContexts={accountContexts}
                            onAccountsChange={handleAccountsChange}
                            dark={!!accentColor}
                        />
                    )}
                </div>
            </header>

            <main className="px-4 sm:px-8 pb-20 lg:pb-0">
                <div className="max-w-[480px] lg:max-w-[960px] mx-auto">
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
