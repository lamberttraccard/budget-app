import { Deferred, Head, router, usePage } from '@inertiajs/react';
import { UploadIcon } from 'lucide-react';
import { AnimatePresence, motion, useReducedMotion } from 'motion/react';
import { useCallback, useRef, useState } from 'react';
import AmbientCircle from '@/components/AmbientCircle';
import CategoryCard from '@/components/CategoryCard';
import EmptyState from '@/components/EmptyState';
import ImportDropZone from '@/components/ImportDropZone';
import ImportHistory from '@/components/ImportHistory';
import MonthStrip from '@/components/MonthStrip';
import SummaryWidget from '@/components/SummaryWidget';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import AppLayout from '@/layouts/app-layout';
import type { CategoryTotal, DashboardProps } from '@/types';

function formatMonthLabel(month: string): string {
    const [year, m] = month.split('-');
    const date = new Date(Number(year), Number(m) - 1);
    return date.toLocaleDateString('en-US', { month: 'long', year: 'numeric' });
}

function formatDateRange(from: string, to: string): string {
    const opts: Intl.DateTimeFormatOptions = { month: 'short', day: 'numeric' };
    const fromDate = new Date(from + 'T00:00:00');
    const toDate = new Date(to + 'T00:00:00');
    return `${fromDate.toLocaleDateString('en-US', opts)} – ${toDate.toLocaleDateString('en-US', { ...opts, year: 'numeric' })}`;
}

const containerVariants = {
    hidden: {},
    visible: {
        transition: {
            staggerChildren: 0.08,
        },
    },
};

const cardVariants = {
    hidden: { opacity: 0, y: 16 },
    visible: {
        opacity: 1,
        y: 0,
        transition: { duration: 0.3, ease: 'easeOut' as const },
    },
};

export default function Dashboard({
    categoryTotals,
    incomeCents,
    expensesCents,
    currentMonth,
    currentAccount,
    availableMonths,
    importPreview,
    trendData,
    imports,
}: DashboardProps) {
    const { errors } = usePage().props as { errors: Record<string, string> };
    const fileInputRef = useRef<HTMLInputElement>(null);
    const [file, setFile] = useState<File | null>(null);
    const [isDragging, setIsDragging] = useState(false);
    const [isUploading, setIsUploading] = useState(false);
    const [showConfirmation, setShowConfirmation] = useState(false);
    const [importAccountType, setImportAccountType] = useState<string>('personal');
    const [shouldCascade, setShouldCascade] = useState(false);
    const shouldReduceMotion = useReducedMotion();

    const hasAnyData = availableMonths.length > 0;
    const isEmptyMonth = hasAnyData && categoryTotals.length === 0;

    const handleMonthChange = useCallback((month: string) => {
        setShouldCascade(false);
        router.get('/', { month, account: currentAccount }, {
            preserveState: true,
            preserveScroll: true,
            only: ['categoryTotals', 'incomeCents', 'expensesCents', 'currentMonth', 'availableMonths', 'trendData', 'imports'],
        });
    }, [currentAccount]);

    const handleAccountChange = useCallback((account: string) => {
        setShouldCascade(false);
        router.get('/', { month: currentMonth, account }, {
            preserveState: true,
            preserveScroll: true,
            only: ['categoryTotals', 'incomeCents', 'expensesCents', 'currentMonth', 'currentAccount', 'availableMonths', 'trendData', 'imports'],
        });
    }, [currentMonth]);

    const triggerFileInput = useCallback(() => {
        fileInputRef.current?.click();
    }, []);

    const handleFileSelect = useCallback((selectedFile: File) => {
        setFile(selectedFile);
        setIsUploading(true);

        router.post('/imports/preview', { csv_file: selectedFile }, {
            preserveState: true,
            preserveScroll: true,
            onSuccess: () => {
                setShowConfirmation(true);
                setIsUploading(false);
            },
            onError: () => {
                setIsUploading(false);
                setFile(null);
            },
        });
    }, []);

    const handleInputChange = useCallback((e: React.ChangeEvent<HTMLInputElement>) => {
        const selectedFile = e.target.files?.[0];
        if (selectedFile) {
            handleFileSelect(selectedFile);
        }
        // Reset input so the same file can be selected again
        e.target.value = '';
    }, [handleFileSelect]);

    const handleConfirmImport = useCallback(() => {
        if (!file) return;
        setIsUploading(true);

        router.post('/imports', { csv_file: file, account_type: importAccountType }, {
            onSuccess: () => {
                setFile(null);
                setShowConfirmation(false);
                setIsUploading(false);
                setImportAccountType('personal');
                setShouldCascade(true);
            },
            onError: () => {
                setShowConfirmation(false);
                setIsUploading(false);
                setFile(null);
                setImportAccountType('personal');
            },
        });
    }, [file, importAccountType]);

    const handleCancelImport = useCallback(() => {
        setShowConfirmation(false);
        setFile(null);
        setImportAccountType('personal');
    }, []);

    // --- Drag-and-drop handlers (desktop) ---
    const handleDragEnter = useCallback((e: React.DragEvent) => {
        e.preventDefault();
        e.stopPropagation();
        setIsDragging(true);
    }, []);

    const handleDragLeave = useCallback((e: React.DragEvent) => {
        e.preventDefault();
        e.stopPropagation();
        // Only hide overlay if leaving the page entirely (not entering a child)
        if (e.currentTarget.contains(e.relatedTarget as Node)) return;
        setIsDragging(false);
    }, []);

    const handleDragOver = useCallback((e: React.DragEvent) => {
        e.preventDefault(); // Required to enable drop
        e.stopPropagation();
    }, []);

    const handleDrop = useCallback((e: React.DragEvent) => {
        e.preventDefault();
        e.stopPropagation();
        setIsDragging(false);

        const droppedFile = e.dataTransfer.files[0];
        if (droppedFile) {
            handleFileSelect(droppedFile);
        }
    }, [handleFileSelect]);

    return (
        <AppLayout currentAccount={currentAccount} onAccountChange={handleAccountChange}>
            <Head title="Budget" />

            {/* Hidden file input */}
            <input
                ref={fileInputRef}
                type="file"
                accept=".csv,text/csv"
                className="hidden"
                onChange={handleInputChange}
            />

            <AmbientCircle />

            {/* Drag-and-drop zone wraps the entire page content */}
            <div
                className="relative py-6 space-y-5"
                onDragEnter={handleDragEnter}
                onDragLeave={handleDragLeave}
                onDragOver={handleDragOver}
                onDrop={handleDrop}
            >
                {/* Import error display */}
                {errors.csv_file && (
                    <div className="rounded-xl bg-red-500/10 border border-red-500/20 p-4">
                        <p className="text-sm text-red-400">{errors.csv_file}</p>
                    </div>
                )}

                {hasAnyData && (
                    <MonthStrip
                        currentMonth={currentMonth}
                        availableMonths={availableMonths}
                        onMonthChange={handleMonthChange}
                    />
                )}

                <SummaryWidget incomeCents={incomeCents} expensesCents={expensesCents} showDashes={isEmptyMonth} />

                {isEmptyMonth ? (
                    <div className="py-12 text-center">
                        <p className="text-sm text-text-tertiary">
                            No transactions for {formatMonthLabel(currentMonth)}
                        </p>
                    </div>
                ) : (
                    <AnimatePresence mode="wait">
                        {categoryTotals.length === 0 ? (
                            <motion.div
                                key="empty"
                                exit={shouldReduceMotion ? undefined : { opacity: 0, scale: 0.95 }}
                                transition={{ duration: 0.2 }}
                            >
                                <EmptyState onImportClick={triggerFileInput} disabled={isUploading} />
                            </motion.div>
                        ) : (
                            <div key="categories">
                                {shouldReduceMotion ? (
                                    <div className="space-y-3">
                                        {categoryTotals.map((cat: CategoryTotal) => (
                                            <CategoryCard
                                                key={cat.id}
                                                id={cat.id}
                                                name={cat.name}
                                                color={cat.color}
                                                icon={cat.icon}
                                                amountCents={cat.amountCents}
                                                transactionCount={cat.transactionCount}
                                                trendPercentage={trendData?.hasPreviousMonthData ? (trendData.categoryTrends[cat.slug] ?? cat.trendPercentage) : undefined}
                                                currentMonth={currentMonth}
                                                currentAccount={currentAccount}
                                            />
                                        ))}
                                    </div>
                                ) : (
                                    <motion.div
                                        className="space-y-3"
                                        variants={containerVariants}
                                        initial={shouldCascade ? 'hidden' : false}
                                        animate="visible"
                                        onAnimationComplete={() => setShouldCascade(false)}
                                    >
                                        {categoryTotals.map((cat: CategoryTotal) => (
                                            <motion.div key={cat.id} variants={cardVariants}>
                                                <CategoryCard
                                                    id={cat.id}
                                                    name={cat.name}
                                                    color={cat.color}
                                                    icon={cat.icon}
                                                    amountCents={cat.amountCents}
                                                    transactionCount={cat.transactionCount}
                                                    trendPercentage={trendData?.hasPreviousMonthData ? (trendData.categoryTrends[cat.slug] ?? cat.trendPercentage) : undefined}
                                                    currentMonth={currentMonth}
                                                    currentAccount={currentAccount}
                                                />
                                            </motion.div>
                                        ))}
                                    </motion.div>
                                )}

                                {/* Inline import button for subsequent imports */}
                                <button
                                    type="button"
                                    onClick={triggerFileInput}
                                    disabled={isUploading}
                                    className="mt-5 flex w-full items-center justify-center gap-2 rounded-xl bg-surface/80 backdrop-blur-2xl p-3 text-sm text-text-secondary transition-colors hover:bg-surface-elevated/80 hover:text-white disabled:opacity-50"
                                >
                                    <UploadIcon className="size-4" />
                                    {isUploading ? 'Uploading...' : 'Import CSV'}
                                </button>
                            </div>
                        )}
                    </AnimatePresence>
                )}

                <Deferred data="imports" fallback={null}>
                    {(imports ?? []).length > 0 && (
                        <ImportHistory imports={imports!} />
                    )}
                </Deferred>
            </div>

            {/* Desktop drag-and-drop overlay */}
            <ImportDropZone visible={isDragging} />

            {/* Import confirmation dialog */}
            <Dialog open={showConfirmation} onOpenChange={(open) => {
                if (!open) handleCancelImport();
            }}>
                <DialogContent>
                    <DialogHeader>
                        <DialogTitle>Import transactions</DialogTitle>
                        <DialogDescription>
                            Review the import summary before committing.
                        </DialogDescription>
                    </DialogHeader>

                    {importPreview && (
                        <div className="space-y-3 py-2">
                            <div className="flex justify-between text-sm">
                                <span className="text-text-secondary">Bank</span>
                                <span className="font-medium text-white">{importPreview.bank}</span>
                            </div>
                            <div className="flex justify-between text-sm">
                                <span className="text-text-secondary">Transactions</span>
                                <span className="font-medium text-white">{importPreview.transactionCount}</span>
                            </div>
                            <div className="flex justify-between text-sm">
                                <span className="text-text-secondary">Period</span>
                                <span className="font-medium text-white">
                                    {formatDateRange(importPreview.dateFrom, importPreview.dateTo)}
                                </span>
                            </div>
                            <div className="flex justify-between text-sm items-center">
                                <span className="text-text-secondary">Account</span>
                                <div className="flex gap-2">
                                    <button
                                        type="button"
                                        onClick={() => setImportAccountType('personal')}
                                        className={`rounded-full px-3 py-1 text-xs font-medium transition-colors ${
                                            importAccountType === 'personal'
                                                ? 'bg-surface-elevated text-white'
                                                : 'text-text-secondary hover:text-white'
                                        }`}
                                    >
                                        Personal
                                    </button>
                                    <button
                                        type="button"
                                        onClick={() => setImportAccountType('professional')}
                                        className={`rounded-full px-3 py-1 text-xs font-medium transition-colors ${
                                            importAccountType === 'professional'
                                                ? 'bg-surface-elevated text-white'
                                                : 'text-text-secondary hover:text-white'
                                        }`}
                                    >
                                        Professional
                                    </button>
                                </div>
                            </div>
                        </div>
                    )}

                    <DialogFooter>
                        <Button variant="outline" onClick={handleCancelImport} disabled={isUploading}>
                            Cancel
                        </Button>
                        <Button onClick={handleConfirmImport} disabled={isUploading}>
                            {isUploading ? 'Importing...' : 'Import'}
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>
        </AppLayout>
    );
}
