import { router } from '@inertiajs/react';
import { Trash2, ChevronDown, ChevronLeft, ChevronRight, ChevronUp } from 'lucide-react';
import { useState } from 'react';
import { toast } from 'sonner';

import CategorizationProgress from '@/components/CategorizationProgress';
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

const PAGE_SIZE = 5;

export default function ImportHistory({ imports }: { imports: ImportRecord[] }) {
    const [isOpen, setIsOpen] = useState(() => imports.some((i) => i.status === 'categorizing'));
    const [deletingImportId, setDeletingImportId] = useState<number | null>(null);
    const [isDeleting, setIsDeleting] = useState(false);
    const [page, setPage] = useState(1);

    const pageCount = Math.max(1, Math.ceil(imports.length / PAGE_SIZE));
    // Clamp so deleting the last import of the last page falls back to the previous page
    const currentPage = Math.min(page, pageCount);
    const pageStart = (currentPage - 1) * PAGE_SIZE;
    const pageImports = imports.slice(pageStart, pageStart + PAGE_SIZE);

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
            <div className="glass rounded-panel px-4 py-3">
                <button
                    type="button"
                    onClick={() => setIsOpen(!isOpen)}
                    className="flex w-full items-center justify-between"
                >
                    <span className="text-sm text-white/60">Import History</span>
                    {isOpen ? (
                        <ChevronUp className="size-4 text-white/60" />
                    ) : (
                        <ChevronDown className="size-4 text-white/60" />
                    )}
                </button>

                {isOpen && (
                    <div className="mt-3">
                        {pageImports.map((imp, index) => (
                            <div
                                key={imp.id}
                                className={`flex items-center justify-between py-3 ${
                                    index < pageImports.length - 1 ? 'border-b border-white/[0.06]' : ''
                                }`}
                            >
                                <div className="min-w-0 flex-1">
                                    <p className="text-sm text-white">{imp.bank}</p>
                                    <p className="text-xs text-white/50">
                                        {imp.transactionCount} transactions
                                        {imp.dateFrom && imp.dateTo && (
                                            <> &middot; {formatRange(imp.dateFrom, imp.dateTo)}</>
                                        )}
                                    </p>
                                    <CategorizationProgress import={imp} />
                                </div>
                                <Button
                                    variant="ghost"
                                    size="icon"
                                    onClick={() => setDeletingImportId(imp.id)}
                                    className="ml-2 rounded-full text-white/40 hover:text-[#EF4444]"
                                >
                                    <Trash2 className="size-4" />
                                </Button>
                            </div>
                        ))}

                        {pageCount > 1 && (
                            <div className="flex items-center justify-between border-t border-white/[0.06] pt-3">
                                <span className="text-xs text-white/50">
                                    {pageStart + 1}–{pageStart + pageImports.length} of {imports.length}
                                </span>
                                <div className="flex items-center gap-1">
                                    <Button
                                        variant="ghost"
                                        size="icon"
                                        aria-label="Previous page"
                                        onClick={() => setPage(currentPage - 1)}
                                        disabled={currentPage === 1}
                                        className="rounded-full text-white/60"
                                    >
                                        <ChevronLeft className="size-4" />
                                    </Button>
                                    <span className="text-xs text-white/60 tabular-nums">
                                        {currentPage} / {pageCount}
                                    </span>
                                    <Button
                                        variant="ghost"
                                        size="icon"
                                        aria-label="Next page"
                                        onClick={() => setPage(currentPage + 1)}
                                        disabled={currentPage === pageCount}
                                        className="rounded-full text-white/60"
                                    >
                                        <ChevronRight className="size-4" />
                                    </Button>
                                </div>
                            </div>
                        )}
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
