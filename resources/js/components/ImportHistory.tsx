import { router } from '@inertiajs/react';
import { Trash2, ChevronDown, ChevronUp } from 'lucide-react';
import { useState } from 'react';
import { toast } from 'sonner';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import type { ImportRecord } from '@/types';

export default function ImportHistory({ imports }: { imports: ImportRecord[] }) {
    const [isOpen, setIsOpen] = useState(false);
    const [deletingImportId, setDeletingImportId] = useState<number | null>(null);
    const [isDeleting, setIsDeleting] = useState(false);

    const selectedImport = imports.find((i) => i.id === deletingImportId);

    function handleDelete() {
        if (!deletingImportId) return;

        setIsDeleting(true);
        router.delete(`/imports/${deletingImportId}`, {
            preserveScroll: true,
            onSuccess: () => {
                toast('Import deleted');
                setDeletingImportId(null);
            },
            onError: () => {
                toast.error('Failed to delete import');
            },
            onFinish: () => {
                setIsDeleting(false);
            },
        });
    }

    function formatRange(dateFrom: string | null, dateTo: string | null): string {
        if (!dateFrom || !dateTo) return '';
        const opts: Intl.DateTimeFormatOptions = { month: 'short', day: 'numeric' };
        const from = new Date(dateFrom + 'T00:00:00');
        const to = new Date(dateTo + 'T00:00:00');
        return `${from.toLocaleDateString('en-US', opts)} – ${to.toLocaleDateString('en-US', { ...opts, year: 'numeric' })}`;
    }

    return (
        <>
            <div className="mt-6 rounded-xl bg-surface/80 backdrop-blur-2xl border border-white/[0.06] p-4">
                <button
                    type="button"
                    onClick={() => setIsOpen(!isOpen)}
                    className="flex w-full items-center justify-between"
                >
                    <span className="text-sm font-medium text-[#A1A1AA]">Import History</span>
                    {isOpen ? (
                        <ChevronUp className="size-4 text-[#A1A1AA]" />
                    ) : (
                        <ChevronDown className="size-4 text-[#A1A1AA]" />
                    )}
                </button>

                {isOpen && (
                    <div className="mt-3">
                        {imports.map((imp, index) => (
                            <div
                                key={imp.id}
                                className={`flex items-center justify-between py-3 ${
                                    index < imports.length - 1 ? 'border-b border-[#2C2C2E]' : ''
                                }`}
                            >
                                <div className="min-w-0 flex-1">
                                    <p className="text-sm font-medium text-white">{imp.bank}</p>
                                    <p className="text-xs text-[#A1A1AA]">
                                        {imp.transactionCount} transactions
                                        {imp.dateFrom && imp.dateTo && (
                                            <> &middot; {formatRange(imp.dateFrom, imp.dateTo)}</>
                                        )}
                                    </p>
                                </div>
                                <Button
                                    variant="ghost"
                                    size="icon"
                                    onClick={() => setDeletingImportId(imp.id)}
                                    className="ml-2 text-[#71717A] hover:text-[#EF4444]"
                                >
                                    <Trash2 className="size-4" />
                                </Button>
                            </div>
                        ))}
                    </div>
                )}
            </div>

            <Dialog
                open={deletingImportId !== null}
                onOpenChange={(open) => {
                    if (!open) setDeletingImportId(null);
                }}
            >
                <DialogContent>
                    <DialogHeader>
                        <DialogTitle>Delete this import?</DialogTitle>
                        <DialogDescription>
                            {selectedImport?.transactionCount} transactions from {selectedImport?.bank}
                            {selectedImport?.dateFrom && selectedImport?.dateTo && (
                                <> ({formatRange(selectedImport.dateFrom, selectedImport.dateTo)})</>
                            )}{' '}
                            will be removed.
                        </DialogDescription>
                    </DialogHeader>
                    <DialogFooter>
                        <Button variant="outline" onClick={() => setDeletingImportId(null)}>
                            Cancel
                        </Button>
                        <Button variant="destructive" onClick={handleDelete} disabled={isDeleting}>
                            {isDeleting ? 'Deleting...' : 'Delete'}
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>
        </>
    );
}
