import { LayoutGrid, Repeat, Search } from 'lucide-react';
import type { ComponentType } from 'react';
import { useCurrentUrl } from '@/hooks/use-current-url';

export type DockNavItem = {
    label: string;
    href: string;
    icon: ComponentType<{ className?: string }>;
    isActive: boolean;
};

export function useNavItems(currentAccount?: string): DockNavItem[] {
    const { currentUrl } = useCurrentUrl();

    function buildHref(path: string): string {
        if (currentAccount && path !== '/dashboard') {
            return `${path}?account=${currentAccount}`;
        }
        return path;
    }

    return [
        {
            label: 'Dashboard',
            href: buildHref('/dashboard'),
            icon: LayoutGrid,
            isActive: currentUrl === '/dashboard' || currentUrl.startsWith('/categories'),
        },
        {
            label: 'Recurring',
            href: buildHref('/recurring'),
            icon: Repeat,
            isActive: currentUrl === '/recurring',
        },
        {
            label: 'Search',
            href: buildHref('/search'),
            icon: Search,
            isActive: currentUrl === '/search',
        },
    ];
}
