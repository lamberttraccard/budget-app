import { Link, usePage } from '@inertiajs/react';
import { Toaster } from 'sonner';
import AccountPicker from '@/components/AccountPicker';
import AppLogoIcon from '@/components/app-logo-icon';
import BottomDock from '@/components/BottomDock';
import { useNavItems } from '@/hooks/use-nav-items';
import type { AccountContext, AppLayoutProps } from '@/types';

export default function AppLayout({ children, currentAccount, onAccountChange, accentColor }: AppLayoutProps) {
    const { accountContexts } = usePage<{ props: { accountContexts: AccountContext[] } }>().props as unknown as { accountContexts: AccountContext[] };
    const navItems = useNavItems(currentAccount);

    return (
        <div className="min-h-screen bg-background">
            <header className="relative z-20 px-4 sm:px-8 py-4 pt-[calc(env(safe-area-inset-top)+1rem)]">
                <div className="max-w-[480px] lg:max-w-[960px] mx-auto flex items-center justify-between">
                    <div className="flex items-center gap-4">
                        <AppLogoIcon className="size-8 rounded-lg" />
                        <nav className="hidden lg:flex items-center gap-1" aria-label="Main navigation">
                            {navItems.map((item) => (
                                <Link
                                    key={item.label}
                                    href={item.href}
                                    aria-current={item.isActive ? 'page' : undefined}
                                    className={`px-3 py-1.5 rounded-lg text-sm font-medium transition-colors focus-visible:ring-2 focus-visible:ring-white/50 focus-visible:outline-none ${
                                        item.isActive
                                            ? 'bg-surface-elevated'
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
                    {accountContexts?.length > 0 && currentAccount && onAccountChange && (
                        <AccountPicker
                            currentAccount={currentAccount}
                            accountContexts={accountContexts}
                            onAccountChange={onAccountChange}
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

            <BottomDock currentAccount={currentAccount} />
        </div>
    );
}
