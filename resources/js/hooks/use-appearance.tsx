/**
 * Appearance hook — budget-app is always dark.
 * Kept as a minimal stub for any components that import it.
 */

export type ResolvedAppearance = 'dark';
export type Appearance = 'dark';

export type UseAppearanceReturn = {
    readonly appearance: Appearance;
    readonly resolvedAppearance: ResolvedAppearance;
    readonly updateAppearance: (mode: Appearance) => void;
};

export function initializeTheme(): void {
    if (typeof document === 'undefined') return;
    document.documentElement.classList.add('dark');
    document.documentElement.style.colorScheme = 'dark';
}

export function useAppearance(): UseAppearanceReturn {
    return {
        appearance: 'dark',
        resolvedAppearance: 'dark',
        updateAppearance: () => {},
    } as const;
}
