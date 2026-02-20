import AppLayoutTemplate from '@/layouts/app/app-layout';
import type { AppLayoutProps } from '@/types';

export default ({ children, ...props }: AppLayoutProps) => (
    <AppLayoutTemplate {...props}>
        {children}
    </AppLayoutTemplate>
);
