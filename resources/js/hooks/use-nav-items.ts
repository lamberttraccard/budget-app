import { LayoutGrid, Repeat, Search } from 'lucide-react';
import type { ComponentType } from 'react';
import { useCurrentUrl } from '@/hooks/use-current-url';

export type DockNavItem = {
    label: string;
    href: string;
    icon: ComponentType<{ className?: string }>;
    isActive: boolean;
};

export function useNavItems(selectedAccountIds?: number[]): DockNavItem[] {
    const { currentUrl } = useCurrentUrl();

    function buildHref(path: string): string {
        if (selectedAccountIds && selectedAccountIds.length > 0 && path !== '/') {
            return `${path}?accounts=${selectedAccountIds.join(',')}`;
        }
        return path;
    }

    return [
        {
            label: 'Dashboard',
            href: buildHref('/'),
            icon: LayoutGrid,
            isActive: currentUrl === '/' || currentUrl.startsWith('/categories'),
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
