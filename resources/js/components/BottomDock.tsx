import { Link, usePage } from '@inertiajs/react';
import { useNavItems } from '@/hooks/use-nav-items';
import type { SharedProps } from '@/types';

export default function BottomDock() {
    const { selectedAccountIds } = usePage<SharedProps>().props;
    const items = useNavItems(selectedAccountIds ?? []);

    return (
        <>
            <div
                className="pointer-events-none fixed inset-x-0 bottom-0 z-40 h-28 bg-gradient-to-t from-background via-background/80 to-transparent"
                aria-hidden="true"
            />
            <nav
                aria-label="Bottom navigation"
                className="glass fixed left-1/2 z-50 flex -translate-x-1/2 items-center gap-1 rounded-full bg-[#1E2028]/60 p-1.5 bottom-[calc(env(safe-area-inset-bottom)+1rem)]"
            >
                {items.map((item) => (
                    <Link
                        key={item.label}
                        href={item.href}
                        aria-current={item.isActive ? 'page' : undefined}
                        title={item.label}
                        className={`flex h-11 min-w-11 items-center justify-center gap-2 rounded-full px-3 text-sm font-medium transition-colors focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-white/50 ${
                            item.isActive ? 'bg-foreground text-background' : 'text-white/55 hover:text-white'
                        }`}
                    >
                        <item.icon className="size-5" />
                        <span className="sr-only lg:not-sr-only">{item.label}</span>
                    </Link>
                ))}
            </nav>
        </>
    );
}
