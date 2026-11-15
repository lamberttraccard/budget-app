import type { ReactNode } from 'react';

export type AppLayoutProps = {
    children: ReactNode;
    onAccountsChange?: (accountIds: number[]) => void;
    /** Tints the aurora glow at the top of the page. */
    accentColor?: string;
    /** Replaces the logo with a back button. */
    backHref?: string;
    /** Content centered in the header, such as the month picker. */
    headerCenter?: ReactNode;
};

export type AuthLayoutProps = {
    children?: ReactNode;
    name?: string;
    title?: string;
    description?: string;
};
