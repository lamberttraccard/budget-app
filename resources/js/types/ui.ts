import type { ReactNode } from 'react';

export type AppLayoutProps = {
    children: ReactNode;
    currentAccount?: string;
    onAccountChange?: (account: string) => void;
};

export type AuthLayoutProps = {
    children?: ReactNode;
    name?: string;
    title?: string;
    description?: string;
};
