import type { AppLayoutProps } from '@/types';

export default function AppLayout({ children }: AppLayoutProps) {
    return (
        <div className="min-h-screen bg-background">
            <header className="px-4 sm:px-8 py-4">
                <div className="max-w-[480px] mx-auto flex items-center justify-between">
                    <span className="text-lg font-semibold text-white">
                        budget-app
                    </span>
                </div>
            </header>

            <main className="px-4 sm:px-8">
                <div className="max-w-[480px] mx-auto">
                    {children}
                </div>
            </main>
        </div>
    );
}
