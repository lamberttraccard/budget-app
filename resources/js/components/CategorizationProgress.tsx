import { router } from '@inertiajs/react';
import { Loader2, RefreshCw, CheckCircle2, AlertTriangle } from 'lucide-react';
import { toast } from 'sonner';

import { Button } from '@/components/ui/button';
import type { ImportRecord } from '@/types';

export default function CategorizationProgress({
    import: imp,
}: {
    import: ImportRecord;
}) {
    function handleRecategorize() {
        router.post(`/imports/${imp.id}/recategorize`, {}, {
            preserveScroll: true,
            onSuccess: () => toast('Re-categorizing transactions...'),
            onError: () => toast.error('Failed to start re-categorization'),
        });
    }

    if (imp.status === 'categorizing') {
        return (
            <div className="flex items-center gap-2 mt-1">
                <Loader2 className="size-3 animate-spin text-blue-400" />
                <span className="text-xs text-blue-400">Categorizing...</span>
            </div>
        );
    }

    if (imp.status === 'categorized' && imp.uncategorizedCount > 0) {
        return (
            <div className="flex items-center gap-2 mt-1">
                <AlertTriangle className="size-3 text-amber-400" />
                <span className="text-xs text-amber-400">
                    {imp.uncategorizedCount} uncategorized
                </span>
                <Button
                    variant="ghost"
                    size="sm"
                    onClick={handleRecategorize}
                    className="h-5 px-1.5 text-xs text-amber-400 hover:text-amber-300"
                >
                    <RefreshCw className="size-3 mr-1" />
                    Retry
                </Button>
            </div>
        );
    }

    if (imp.status === 'categorized') {
        return (
            <div className="flex items-center gap-2 mt-1">
                <CheckCircle2 className="size-3 text-emerald-400" />
                <span className="text-xs text-emerald-400">Categorized</span>
            </div>
        );
    }

    return null;
}
