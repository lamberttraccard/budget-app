import { Head, router } from '@inertiajs/react';
import { Play, Trash2 } from 'lucide-react';
import { useCallback, useState } from 'react';
import { toast } from 'sonner';
import Heading from '@/components/heading';
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
import SettingsLayout from '@/layouts/settings/layout';
import { apply, destroy } from '@/routes/merchant-rules';

import type { MerchantRuleItem, MerchantRulesProps } from '@/types';

export default function MerchantRules({ rules }: MerchantRulesProps) {
    const [isProcessing, setIsProcessing] = useState(false);
    const [applyingRule, setApplyingRule] = useState<MerchantRuleItem | null>(null);
    const [deletingRule, setDeletingRule] = useState<MerchantRuleItem | null>(null);

    const handleApply = useCallback((rule: MerchantRuleItem) => {
        setApplyingRule(rule);
    }, []);

    const confirmApply = useCallback(() => {
        if (!applyingRule) return;
        setIsProcessing(true);
        router.post(apply.url(applyingRule.id), {}, {
            preserveScroll: true,
            onSuccess: (page) => {
                const props = page.props as unknown as { flash?: { updated_count?: number } };
                const count = props.flash?.updated_count;
                toast(`Updated ${count ?? 0} transactions`);
                setApplyingRule(null);
            },
            onFinish: () => setIsProcessing(false),
        });
    }, [applyingRule]);

    const handleDelete = useCallback((rule: MerchantRuleItem) => {
        setDeletingRule(rule);
    }, []);

    const confirmDelete = useCallback(() => {
        if (!deletingRule) return;
        setIsProcessing(true);
        router.delete(destroy.url(deletingRule.id), {
            preserveScroll: true,
            onSuccess: () => {
                toast('Rule deleted');
                setDeletingRule(null);
            },
            onFinish: () => setIsProcessing(false),
        });
    }, [deletingRule]);

    return (
        <AppLayout>
            <Head title="Merchant Rules" />

            <h1 className="sr-only">Merchant Rules</h1>

            <SettingsLayout>
                <div className="space-y-6">
                    <Heading
                        title="Merchant Rules"
                        description="Auto-categorization rules created when you recategorize transactions."
                    />

                    {rules.length === 0 ? (
                        <div className="py-8 text-center">
                            <p className="text-sm text-muted-foreground">
                                No merchant rules yet. Rules are created automatically when you recategorize a transaction.
                            </p>
                        </div>
                    ) : (
                        <div className="space-y-1">
                            {rules.map((rule) => (
                                <div
                                    key={rule.id}
                                    className="flex items-center gap-3 rounded-lg border border-border/50 px-3 py-2.5"
                                >
                                    <div className="flex-1 min-w-0">
                                        <p className="text-sm text-foreground truncate" title={rule.merchantPattern}>
                                            {rule.merchantPattern}
                                        </p>
                                        <div className="flex items-center gap-1.5 mt-0.5">
                                            <span
                                                className="inline-block size-2 rounded-full shrink-0"
                                                style={{ backgroundColor: rule.categoryColor }}
                                            />
                                            <span className="text-xs text-muted-foreground truncate">
                                                {rule.categoryName}
                                            </span>
                                            <span className="text-xs text-muted-foreground/60 shrink-0">
                                                &middot; {rule.matchCount} transaction{rule.matchCount === 1 ? '' : 's'}
                                            </span>
                                        </div>
                                    </div>
                                    <div className="flex items-center gap-1 shrink-0">
                                        <Button
                                            variant="ghost"
                                            size="sm"
                                            onClick={() => handleApply(rule)}
                                            disabled={isProcessing || rule.matchCount === 0}
                                            title="Apply rule to all matching transactions"
                                        >
                                            <Play className="size-3.5" />
                                            <span className="sr-only sm:not-sr-only sm:ml-1">Apply</span>
                                        </Button>
                                        <Button
                                            variant="ghost"
                                            size="sm"
                                            onClick={() => handleDelete(rule)}
                                            disabled={isProcessing}
                                            title="Delete rule"
                                            className="text-muted-foreground hover:text-destructive"
                                        >
                                            <Trash2 className="size-3.5" />
                                        </Button>
                                    </div>
                                </div>
                            ))}
                        </div>
                    )}
                </div>
            </SettingsLayout>

            <Dialog open={applyingRule !== null} onOpenChange={(open) => !open && setApplyingRule(null)}>
                <DialogContent>
                    <DialogHeader>
                        <DialogTitle>Apply rule?</DialogTitle>
                        <DialogDescription asChild>
                            <div className="space-y-2">
                                <p>
                                    Apply rule to {applyingRule?.matchCount ?? 0} transactions matching
                                    &ldquo;{applyingRule?.merchantPattern}&rdquo;?
                                </p>
                                <p>
                                    All matching transactions will be recategorized to{' '}
                                    <span
                                        className="inline-block size-2 rounded-full align-middle"
                                        style={{ backgroundColor: applyingRule?.categoryColor }}
                                    />{' '}
                                    <span className="font-medium text-foreground">{applyingRule?.categoryName}</span>,
                                    including any previously categorized.
                                </p>
                            </div>
                        </DialogDescription>
                    </DialogHeader>
                    <DialogFooter>
                        <Button variant="secondary" onClick={() => setApplyingRule(null)} disabled={isProcessing}>
                            Cancel
                        </Button>
                        <Button onClick={confirmApply} disabled={isProcessing}>
                            Apply
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>

            <Dialog open={deletingRule !== null} onOpenChange={(open) => !open && setDeletingRule(null)}>
                <DialogContent>
                    <DialogHeader>
                        <DialogTitle>Delete rule?</DialogTitle>
                        <DialogDescription>
                            Delete the rule for &ldquo;{deletingRule?.merchantPattern}&rdquo;?
                            Future imports will no longer auto-categorize these transactions.
                        </DialogDescription>
                    </DialogHeader>
                    <DialogFooter>
                        <Button variant="secondary" onClick={() => setDeletingRule(null)} disabled={isProcessing}>
                            Cancel
                        </Button>
                        <Button variant="destructive" onClick={confirmDelete} disabled={isProcessing}>
                            Delete
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>
        </AppLayout>
    );
}
