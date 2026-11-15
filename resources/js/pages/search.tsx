import { Head, router, usePage } from '@inertiajs/react';
import { Search, X } from 'lucide-react';
import { useCallback, useEffect, useRef, useState } from 'react';
import { toast } from 'sonner';
import ApplyRuleConfirmDialog from '@/components/ApplyRuleConfirmDialog';
import CategoryPicker from '@/components/CategoryPicker';
import SearchResultRow from '@/components/SearchResultRow';
import type { TransactionTapData } from '@/components/TransactionRow';
import AppLayout from '@/layouts/app-layout';
import type { SearchProps, SharedProps } from '@/types';


function buildSearchParams(q: string | null, accountIds: number[]): Record<string, string> {
    const params: Record<string, string> = {};
    if (q) params.q = q;
    if (accountIds.length > 0) params.accounts = accountIds.join(',');
    return params;
}

export default function SearchPage({
    results,
    totalCount,
    query: initialQuery,
    selectedAccountIds,
}: SearchProps) {
    const { allCategories } = usePage<SharedProps>().props;
    const [localQuery, setLocalQuery] = useState(initialQuery);
    const inputRef = useRef<HTMLInputElement>(null);

    const [selectedTransactionId, setSelectedTransactionId] = useState<number | null>(null);
    const [selectedTransactionCategoryId, setSelectedTransactionCategoryId] = useState<number | null>(null);
    const [selectedDescription, setSelectedDescription] = useState<string | null>(null);
    const [pendingCategoryId, setPendingCategoryId] = useState<number | null>(null);
    const [matchCount, setMatchCount] = useState<number | null>(null);
    const [isPatching, setIsPatching] = useState(false);
    const pickerTriggerRef = useRef<HTMLSpanElement>(null);
    const selectedTransactionIdRef = useRef<number | null>(null);

    // Sync localQuery when server-side query changes (e.g. back navigation)
    const [syncedQuery, setSyncedQuery] = useState(initialQuery);
    if (initialQuery !== syncedQuery) {
        setSyncedQuery(initialQuery);
        setLocalQuery(initialQuery);
    }

    const timerRef = useRef<ReturnType<typeof setTimeout>>(undefined);

    useEffect(() => {
        return () => clearTimeout(timerRef.current);
    }, []);

    const debouncedSearch = useCallback(
        (value: string) => {
            clearTimeout(timerRef.current);
            timerRef.current = setTimeout(() => {
                router.get(
                    '/search',
                    buildSearchParams(value, selectedAccountIds),
                    { preserveState: true, replace: true },
                );
            }, 300);
        },
        [selectedAccountIds],
    );

    const handleInputChange = useCallback(
        (e: React.ChangeEvent<HTMLInputElement>) => {
            const value = e.target.value;
            setLocalQuery(value);
            if (value.length >= 2) {
                debouncedSearch(value);
            } else if (value.length === 0) {
                router.get(
                    '/search',
                    buildSearchParams(null, selectedAccountIds),
                    { preserveState: true, replace: true },
                );
            }
        },
        [debouncedSearch, selectedAccountIds],
    );

    const handleClear = useCallback(() => {
        setLocalQuery('');
        inputRef.current?.focus();
        router.get(
            '/search',
            buildSearchParams(null, selectedAccountIds),
            { preserveState: true, replace: true },
        );
    }, [selectedAccountIds]);

    const handleAccountsChange = useCallback((accountIds: number[]) => {
        router.get('/search', buildSearchParams(localQuery, accountIds), { preserveState: true, replace: true });
    }, [localQuery]);

    const clearState = useCallback(() => {
        setSelectedTransactionId(null);
        selectedTransactionIdRef.current = null;
        setSelectedTransactionCategoryId(null);
        setSelectedDescription(null);
        setPendingCategoryId(null);
        setMatchCount(null);
    }, []);

    const handleTransactionTap = useCallback(
        (data: TransactionTapData) => {
            if (isPatching) return;
            setSelectedTransactionId(data.id);
            selectedTransactionIdRef.current = data.id;
            setSelectedTransactionCategoryId(data.categoryId);
            setSelectedDescription(data.description);

            if (pickerTriggerRef.current) {
                const rect = data.element.getBoundingClientRect();
                Object.assign(pickerTriggerRef.current.style, {
                    top: `${rect.bottom}px`,
                    left: `${rect.left}px`,
                    width: `${rect.width}px`,
                });
            }
        },
        [isPatching],
    );

    const patchTransaction = useCallback((applyToAll: boolean) => {
        if (selectedTransactionId === null || pendingCategoryId === null) return;
        setIsPatching(true);

        const data = applyToAll
            ? { category_id: pendingCategoryId, apply_to_all: true as const }
            : { category_id: pendingCategoryId };

        router.patch(`/transactions/${selectedTransactionId}`, data, {
            preserveScroll: true,
            onSuccess: (page) => {
                clearState();
                if (applyToAll) {
                    const props = page.props as unknown as { flash?: { updated_count?: number } };
                    const count = props.flash?.updated_count;
                    toast(`Updated ${count ?? 0} transactions`);
                } else {
                    toast("Got it, I'll remember");
                }
            },
            onFinish: () => setIsPatching(false),
        });
    }, [selectedTransactionId, pendingCategoryId, clearState]);

    const handleCategorySelect = useCallback(
        (categoryId: number) => {
            if (selectedTransactionId === null || isPatching) return;

            // F6: Skip if same category
            if (categoryId === selectedTransactionCategoryId) {
                clearState();
                return;
            }

            setPendingCategoryId(categoryId);

            // Fetch match count
            fetch(`/transactions/match-count?description=${encodeURIComponent(selectedDescription ?? '')}`)
                .then((response) => {
                    if (!response.ok) {
                        toast.error('Failed to check matching transactions');
                        clearState();
                        return null;
                    }
                    return response.json();
                })
                .then((data: { count: number } | null) => {
                    if (!data) return;

                    // Guard: abort if the user tapped a different transaction while fetch was in flight
                    const currentId = selectedTransactionIdRef.current;
                    if (currentId !== selectedTransactionId) return;

                    if (data.count <= 1) {
                        // Single match — apply immediately (no dialog)
                        setIsPatching(true);
                        router.patch(`/transactions/${currentId}`, {
                            category_id: categoryId,
                        }, {
                            preserveScroll: true,
                            onSuccess: () => {
                                clearState();
                                toast("Got it, I'll remember");
                            },
                            onFinish: () => setIsPatching(false),
                        });
                    } else {
                        // Multiple matches — show dialog
                        setMatchCount(data.count);
                    }
                })
                .catch(() => {
                    toast.error('Failed to check matching transactions');
                    clearState();
                });
        },
        [selectedTransactionId, selectedTransactionCategoryId, selectedDescription, isPatching, clearState],
    );

    const handlePickerClose = useCallback(() => {
        // Only clear state if there's no pending category selection
        if (pendingCategoryId === null) {
            clearState();
        }
    }, [pendingCategoryId, clearState]);

    const pendingCategory = allCategories.find((c) => c.id === pendingCategoryId);

    return (
        <AppLayout onAccountsChange={handleAccountsChange}>
            <Head title="Search" />

            <div className="pt-4 pb-6 space-y-4">
                <h1 className="text-3xl font-light tracking-tight text-white">Search</h1>

                {/* Search header */}
                <div>
                    <div className="relative w-full">
                        <Search className="absolute left-4 top-1/2 -translate-y-1/2 size-4 text-white/50 pointer-events-none" />
                        <input
                            ref={inputRef}
                            type="text"
                            value={localQuery}
                            onChange={handleInputChange}
                            placeholder="Search transactions..."
                            autoFocus
                            className="glass w-full rounded-full pl-11 pr-11 py-3 text-base md:text-sm text-white placeholder:text-white/40 focus:outline-none focus:ring-2 focus:ring-white/20 transition-shadow"
                        />
                        {localQuery && (
                            <button
                                type="button"
                                onClick={handleClear}
                                aria-label="Clear search"
                                className="absolute right-4 top-1/2 -translate-y-1/2 text-white/50 hover:text-white transition-colors"
                            >
                                <X className="size-4" />
                            </button>
                        )}
                    </div>
                </div>

                {/* Results info */}
                {localQuery.length >= 2 && totalCount > 0 && (
                    <p className="text-xs text-white/50 px-1">
                        {`${totalCount} transaction${totalCount === 1 ? '' : 's'} found`}
                    </p>
                )}

                {/* Hint text */}
                {localQuery.length > 0 && localQuery.length < 2 && (
                    <p className="text-xs text-text-tertiary text-center py-8">
                        Type at least 2 characters to search
                    </p>
                )}

                {/* Empty state for no query */}
                {localQuery.length === 0 && (
                    <p className="text-xs text-text-tertiary text-center py-8">
                        Search by merchant name or description
                    </p>
                )}

                {/* Results list */}
                {results.length > 0 && (
                    <div className="glass rounded-panel overflow-hidden">
                        {results.map((result, index) => (
                            <div key={result.id}>
                                {index > 0 && (
                                    <div className="mx-4 border-t border-white/[0.06]" />
                                )}
                                <SearchResultRow
                                    id={result.id}
                                    date={result.date}
                                    description={result.description}
                                    amountCents={result.amountCents}
                                    categoryId={result.categoryId}
                                    categoryName={result.categoryName}
                                    categoryColor={result.categoryColor}
                                    accountName={result.accountName}
                                    onTap={handleTransactionTap}
                                />
                            </div>
                        ))}
                    </div>
                )}

                {/* No results */}
                {localQuery.length >= 2 && results.length === 0 && totalCount === 0 && (
                    <div className="py-8 text-center">
                        <p className="text-sm text-text-tertiary">
                            No transactions found
                        </p>
                    </div>
                )}
            </div>

            <CategoryPicker
                categories={allCategories}
                currentCategoryId={selectedTransactionCategoryId ?? 0}
                isOpen={selectedTransactionId !== null && pendingCategoryId === null}
                onClose={handlePickerClose}
                onSelect={handleCategorySelect}
                triggerRef={pickerTriggerRef}
            />

            <ApplyRuleConfirmDialog
                isOpen={matchCount !== null && matchCount > 1 && pendingCategoryId !== null}
                description={selectedDescription ?? ''}
                categoryName={pendingCategory?.name ?? ''}
                categoryColor={pendingCategory?.color ?? ''}
                matchCount={matchCount ?? 0}
                isLoading={isPatching}
                onApplyOne={() => patchTransaction(false)}
                onApplyAll={() => patchTransaction(true)}
                onCancel={clearState}
            />
        </AppLayout>
    );
}
