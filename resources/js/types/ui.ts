import type { ReactNode } from 'react';

export type AppLayoutProps = {
    children: ReactNode;
    onAccountsChange?: (accountIds: number[]) => void;
    accentColor?: string;
};

export type AuthLayoutProps = {
    children?: ReactNode;
    name?: string;
    title?: string;
    description?: string;
};
