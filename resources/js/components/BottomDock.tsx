import { Link } from '@inertiajs/react';
import { useNavItems } from '@/hooks/use-nav-items';

type BottomDockProps = {
    selectedAccountIds?: number[];
};

export default function BottomDock({ selectedAccountIds }: BottomDockProps) {
    const items = useNavItems(selectedAccountIds);

    return (
        <nav
            aria-label="Bottom navigation"
            className="fixed bottom-0 inset-x-0 z-50 bg-background/80 backdrop-blur-lg border-t border-white/5 pb-[env(safe-area-inset-bottom)] lg:hidden"
        >
            <div className="flex items-center justify-around h-14">
                {items.map((item) => (
                    <Link
                        key={item.label}
                        href={item.href}
                        aria-current={item.isActive ? 'page' : undefined}
                        className={`flex flex-col items-center justify-center gap-1 min-w-[64px] min-h-[44px] focus-visible:ring-2 focus-visible:ring-white/50 focus-visible:outline-none focus-visible:rounded-lg transition-colors ${
                            item.isActive ? 'text-white' : 'text-text-tertiary'
                        }`}
                    >
                        <item.icon className="size-5" />
                        <span className="text-[10px] font-medium">{item.label}</span>
                    </Link>
                ))}
            </div>
        </nav>
    );
}
