import { Deferred, Head, router, usePage } from '@inertiajs/react';
import { UploadIcon } from 'lucide-react';
import { AnimatePresence, motion, useReducedMotion } from 'motion/react';
import { useCallback, useEffect, useRef, useState } from 'react';
import { toast } from 'sonner';
import CategoryRow from '@/components/CategoryRow';
import EmptyState from '@/components/EmptyState';
import ImportDropZone from '@/components/ImportDropZone';
import ImportHistory from '@/components/ImportHistory';
import MonthPicker from '@/components/MonthPicker';
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
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import AppLayout from '@/layouts/app-layout';
import type { AccountContext, CategoryTotal, DashboardProps } from '@/types';

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

/** Categories that are not spending: they appear in their own section, outside the spending split. */
const NOT_SPENDING_SLUGS = ['income', 'transfer'];

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
    selectedAccountIds,
    availableMonths,
    importPreview,
    averageData,
    imports,
    sparklineData,
}: DashboardProps) {
    const pageProps = usePage().props as unknown as {
        errors: Record<string, string>;
        accountContexts: AccountContext[];
    };
    const { errors, accountContexts } = pageProps;
    const fileInputRef = useRef<HTMLInputElement>(null);
    const [file, setFile] = useState<File | null>(null);
    const [isDragging, setIsDragging] = useState(false);
    const [isUploading, setIsUploading] = useState(false);
    const [showConfirmation, setShowConfirmation] = useState(false);
    const [importAccountId, setImportAccountId] = useState<number | null>(null);
    const [isCreatingAccount, setIsCreatingAccount] = useState(false);
    const [newAccountName, setNewAccountName] = useState('');
    const [shouldCascade, setShouldCascade] = useState(false);
    const shouldReduceMotion = useReducedMotion();

    const hasAnyData = availableMonths.length > 0;
    const isEmptyMonth = hasAnyData && categoryTotals.length === 0;
    const pollingRef = useRef<ReturnType<typeof setInterval> | null>(null);

    // Poll categorizing imports via fetch() to avoid Inertia reload resetting Deferred state
    useEffect(() => {
        const categorizingImports = (imports ?? []).filter(
            (i) => i.status === 'categorizing',
        );

        if (categorizingImports.length === 0) {
            if (pollingRef.current) {
                clearInterval(pollingRef.current);
                pollingRef.current = null;
            }
            return;
        }

        if (pollingRef.current) return; // already polling

        pollingRef.current = setInterval(async () => {
            const results = await Promise.all(
                categorizingImports.map((imp) =>
                    fetch(`/imports/${imp.id}/status`)
                        .then((r) => r.json())
                        .catch(() => null),
                ),
            );

            const anyCompleted = results.some(
                (r) => r?.status === 'categorized',
            );
            if (anyCompleted) {
                toast.success('Categorization complete');
                router.reload({
                    only: [
                        'imports',
                        'categoryTotals',
                        'incomeCents',
                        'expensesCents',
                        'availableMonths',
                    ],
                });
            }
        }, 3000);

        return () => {
            if (pollingRef.current) {
                clearInterval(pollingRef.current);
                pollingRef.current = null;
            }
        };
    }, [imports]);

    const handleMonthChange = useCallback(
        (month: string) => {
            setShouldCascade(false);
            const params: Record<string, string> = { month };
            if (selectedAccountIds.length > 0)
                params.accounts = selectedAccountIds.join(',');
            router.get('/', params, {
                preserveState: true,
                preserveScroll: true,
                only: [
                    'categoryTotals',
                    'incomeCents',
                    'expensesCents',
                    'currentMonth',
                    'availableMonths',
                    'averageData',
                    'sparklineData',
                    'imports',
                ],
            });
        },
        [selectedAccountIds],
    );

    const handleAccountsChange = useCallback(
        (accountIds: number[]) => {
            setShouldCascade(false);
            const params: Record<string, string> = { month: currentMonth };
            if (accountIds.length > 0) params.accounts = accountIds.join(',');
            router.get('/', params, {
                preserveState: true,
                preserveScroll: true,
                only: [
                    'categoryTotals',
                    'incomeCents',
                    'expensesCents',
                    'currentMonth',
                    'selectedAccountIds',
                    'availableMonths',
                    'averageData',
                    'sparklineData',
                    'imports',
                ],
            });
        },
        [currentMonth],
    );

    const triggerFileInput = useCallback(() => {
        fileInputRef.current?.click();
    }, []);

    const handleFileSelect = useCallback(
        (selectedFile: File) => {
            setFile(selectedFile);
            setIsUploading(true);

            router.post(
                '/imports/preview',
                { csv_file: selectedFile },
                {
                    preserveState: true,
                    preserveScroll: true,
                    onSuccess: (page) => {
                        const preview = (
                            page.props as unknown as DashboardProps
                        ).importPreview;
                        if (preview) {
                            const bankAccounts = accountContexts.filter(
                                (a) => a.bank === preview.bank,
                            );
                            if (bankAccounts.length > 0) {
                                setImportAccountId(bankAccounts[0].id);
                                setIsCreatingAccount(false);
                            } else {
                                setIsCreatingAccount(true);
                            }
                        }
                        setShowConfirmation(true);
                        setIsUploading(false);
                    },
                    onError: () => {
                        setIsUploading(false);
                        setFile(null);
                    },
                },
            );
        },
        [accountContexts],
    );

    const handleInputChange = useCallback(
        (e: React.ChangeEvent<HTMLInputElement>) => {
            const selectedFile = e.target.files?.[0];
            if (selectedFile) {
                handleFileSelect(selectedFile);
            }
            // Reset input so the same file can be selected again
            e.target.value = '';
        },
        [handleFileSelect],
    );

    const handleConfirmImport = useCallback(() => {
        if (!file) return;
        setIsUploading(true);

        const data: Record<string, File | string | number | null> = {
            csv_file: file,
        };
        if (isCreatingAccount) {
            data.new_account_name = newAccountName;
        } else {
            data.account_id = importAccountId;
        }

        router.post('/imports', data, {
            onSuccess: () => {
                setFile(null);
                setShowConfirmation(false);
                setIsUploading(false);
                setImportAccountId(null);
                setIsCreatingAccount(false);
                setNewAccountName('');
                setShouldCascade(true);
                toast('Categorizing transactions...');
            },
            onError: () => {
                setShowConfirmation(false);
                setIsUploading(false);
                setFile(null);
                setImportAccountId(null);
                setIsCreatingAccount(false);
                setNewAccountName('');
            },
        });
    }, [file, isCreatingAccount, newAccountName, importAccountId]);

    const handleCancelImport = useCallback(() => {
        setShowConfirmation(false);
        setFile(null);
        setImportAccountId(null);
        setIsCreatingAccount(false);
        setNewAccountName('');
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

    const handleDrop = useCallback(
        (e: React.DragEvent) => {
            e.preventDefault();
            e.stopPropagation();
            setIsDragging(false);

            const droppedFile = e.dataTransfer.files[0];
            if (droppedFile) {
                handleFileSelect(droppedFile);
            }
        },
        [handleFileSelect],
    );

    const spending = categoryTotals.filter(
        (cat) => !NOT_SPENDING_SLUGS.includes(cat.slug),
    );
    const notSpending = categoryTotals.filter((cat) =>
        NOT_SPENDING_SLUGS.includes(cat.slug),
    );

    const renderRows = (
        categories: CategoryTotal[],
        subtitle: (cat: CategoryTotal) => string,
    ) => (
        <motion.div
            className="divide-y divide-white/[0.06] rounded-panel glass px-4 py-1"
            variants={containerVariants}
            initial={shouldCascade && !shouldReduceMotion ? 'hidden' : false}
            animate="visible"
            onAnimationComplete={() => setShouldCascade(false)}
        >
            {categories.map((cat) => (
                <motion.div key={cat.id} variants={cardVariants}>
                    <CategoryRow
                        id={cat.id}
                        name={cat.name}
                        color={cat.color}
                        icon={cat.icon}
                        amountCents={cat.amountCents}
                        subtitle={subtitle(cat)}
                        avgAmountCents={
                            averageData?.categoryAverages[cat.slug]
                                ?.avgAmountCents
                        }
                        currentMonth={currentMonth}
                        selectedAccountIds={selectedAccountIds}
                    />
                </motion.div>
            ))}
        </motion.div>
    );

    const importButton = (
        <button
            type="button"
            onClick={triggerFileInput}
            disabled={isUploading}
            className="inline-flex h-10 items-center gap-2 rounded-full glass px-4 text-sm text-white/80 transition-colors hover:bg-white/10 hover:text-white disabled:opacity-50"
        >
            <UploadIcon className="size-4" />
            {isUploading ? 'Uploading...' : 'Import CSV'}
        </button>
    );

    return (
        <AppLayout
            onAccountsChange={handleAccountsChange}
            headerCenter={
                hasAnyData ? (
                    <MonthPicker
                        currentMonth={currentMonth}
                        availableMonths={availableMonths}
                        onMonthChange={handleMonthChange}
                    />
                ) : undefined
            }
        >
            <Head title="Budget" />

            {/* Hidden file input */}
            <input
                ref={fileInputRef}
                type="file"
                accept=".csv,text/csv"
                className="hidden"
                onChange={handleInputChange}
            />

            {/* Drag-and-drop zone wraps the entire page content */}
            <div
                className="relative space-y-5 pt-4 pb-6"
                onDragEnter={handleDragEnter}
                onDragLeave={handleDragLeave}
                onDragOver={handleDragOver}
                onDrop={handleDrop}
            >
                {/* Import error display */}
                {errors.csv_file && (
                    <div className="rounded-2xl border border-red-500/20 bg-red-500/10 p-4">
                        <p className="text-sm text-red-400">
                            {errors.csv_file}
                        </p>
                    </div>
                )}

                <SummaryWidget
                    incomeCents={incomeCents}
                    expensesCents={expensesCents}
                    avgExpensesCents={averageData?.avgExpensesCents}
                    showDashes={isEmptyMonth}
                    spending={spending}
                    expenseTrend={
                        sparklineData?.monthlyExpenseTotals.map(
                            (d) => d.amountCents,
                        ) ?? []
                    }
                />

                {isEmptyMonth ? (
                    <div className="space-y-4 py-12 text-center">
                        <p className="text-sm text-text-tertiary">
                            No transactions for {formatMonthLabel(currentMonth)}
                        </p>
                        {importButton}
                    </div>
                ) : (
                    <AnimatePresence mode="wait">
                        {categoryTotals.length === 0 ? (
                            <motion.div
                                key="empty"
                                exit={
                                    shouldReduceMotion
                                        ? undefined
                                        : { opacity: 0, scale: 0.95 }
                                }
                                transition={{ duration: 0.2 }}
                            >
                                <EmptyState
                                    onImportClick={triggerFileInput}
                                    disabled={isUploading}
                                />
                            </motion.div>
                        ) : (
                            <div key="categories" className="space-y-5">
                                {spending.length > 0 && (
                                    <section className="space-y-2">
                                        <h2 className="px-1 text-xs text-white/50">
                                            Spending
                                        </h2>
                                        {renderRows(spending, (cat) =>
                                            expensesCents > 0
                                                ? `${Math.round((Math.abs(cat.amountCents) / expensesCents) * 100)} % of spending`
                                                : '',
                                        )}
                                    </section>
                                )}

                                {notSpending.length > 0 && (
                                    <section className="space-y-2">
                                        <h2 className="px-1 text-xs text-white/50">
                                            {notSpending.some(
                                                (cat) => cat.slug !== 'income',
                                            )
                                                ? 'Income & transfers'
                                                : 'Income'}
                                        </h2>
                                        {renderRows(
                                            notSpending,
                                            (cat) =>
                                                `${cat.transactionCount} transaction${cat.transactionCount !== 1 ? 's' : ''}`,
                                        )}
                                    </section>
                                )}

                                {/* Inline import button for subsequent imports */}
                                <div className="flex justify-center">
                                    {importButton}
                                </div>
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
            <Dialog
                open={showConfirmation}
                onOpenChange={(open) => {
                    if (!open) handleCancelImport();
                }}
            >
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
                                <span className="text-text-secondary">
                                    Bank
                                </span>
                                <span className="font-medium text-white">
                                    {importPreview.bank}
                                </span>
                            </div>
                            <div className="flex justify-between text-sm">
                                <span className="text-text-secondary">
                                    Transactions
                                </span>
                                <span className="font-medium text-white">
                                    {importPreview.transactionCount}
                                </span>
                            </div>
                            <div className="flex justify-between text-sm">
                                <span className="text-text-secondary">
                                    Period
                                </span>
                                <span className="font-medium text-white">
                                    {formatDateRange(
                                        importPreview.dateFrom,
                                        importPreview.dateTo,
                                    )}
                                </span>
                            </div>
                            <div className="space-y-2">
                                <span className="text-sm text-text-secondary">
                                    Account
                                </span>
                                {(() => {
                                    const bankAccounts = accountContexts.filter(
                                        (a) => a.bank === importPreview.bank,
                                    );
                                    const showNewForm =
                                        isCreatingAccount ||
                                        bankAccounts.length === 0;

                                    return (
                                        <div className="space-y-2">
                                            {bankAccounts.length > 0 && (
                                                <Select
                                                    value={
                                                        isCreatingAccount
                                                            ? '__new__'
                                                            : String(
                                                                  importAccountId ??
                                                                      '',
                                                              )
                                                    }
                                                    onValueChange={(value) => {
                                                        if (
                                                            value === '__new__'
                                                        ) {
                                                            setIsCreatingAccount(
                                                                true,
                                                            );
                                                            setImportAccountId(
                                                                null,
                                                            );
                                                        } else {
                                                            setIsCreatingAccount(
                                                                false,
                                                            );
                                                            setImportAccountId(
                                                                Number(value),
                                                            );
                                                        }
                                                    }}
                                                >
                                                    <SelectTrigger className="w-full">
                                                        <SelectValue placeholder="Select account..." />
                                                    </SelectTrigger>
                                                    <SelectContent>
                                                        {bankAccounts.map(
                                                            (a) => (
                                                                <SelectItem
                                                                    key={a.id}
                                                                    value={String(
                                                                        a.id,
                                                                    )}
                                                                >
                                                                    {a.name}
                                                                </SelectItem>
                                                            ),
                                                        )}
                                                        <SelectItem value="__new__">
                                                            + New account
                                                        </SelectItem>
                                                    </SelectContent>
                                                </Select>
                                            )}

                                            {showNewForm && (
                                                <div className="rounded-lg border border-white/10 p-3">
                                                    <input
                                                        type="text"
                                                        placeholder="Account name"
                                                        value={newAccountName}
                                                        onChange={(e) =>
                                                            setNewAccountName(
                                                                e.target.value,
                                                            )
                                                        }
                                                        className="w-full rounded-lg border border-white/10 bg-surface px-3 py-2 text-sm text-white placeholder:text-text-tertiary"
                                                    />
                                                </div>
                                            )}
                                        </div>
                                    );
                                })()}
                            </div>
                        </div>
                    )}

                    <DialogFooter>
                        <Button
                            variant="outline"
                            onClick={handleCancelImport}
                            disabled={isUploading}
                        >
                            Cancel
                        </Button>
                        <Button
                            onClick={handleConfirmImport}
                            disabled={isUploading}
                        >
                            {isUploading ? 'Importing...' : 'Import'}
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>
        </AppLayout>
    );
}
