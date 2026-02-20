import { Head } from '@inertiajs/react';
import AppLayout from '@/layouts/app-layout';

export default function Dashboard() {
    return (
        <AppLayout>
            <Head title="Dashboard" />
            <div className="py-6 space-y-5">
                <div className="rounded-xl bg-card p-4">
                    <p className="text-sm text-text-secondary">
                        Import your first CSV to see where your money goes.
                    </p>
                </div>
            </div>
        </AppLayout>
    );
}
