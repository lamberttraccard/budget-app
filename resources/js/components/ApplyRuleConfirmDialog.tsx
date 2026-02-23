import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';

type Props = {
    isOpen: boolean;
    description: string;
    categoryName: string;
    categoryColor: string;
    matchCount: number;
    isLoading: boolean;
    onApplyOne: () => void;
    onApplyAll: () => void;
    onCancel: () => void;
};

export default function ApplyRuleConfirmDialog({
    isOpen,
    description,
    categoryName,
    categoryColor,
    matchCount,
    isLoading,
    onApplyOne,
    onApplyAll,
    onCancel,
}: Props) {
    return (
        <Dialog open={isOpen} onOpenChange={(open) => !open && onCancel()}>
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>
                        Recategorize to{' '}
                        <span
                            className="inline-block size-2 rounded-full align-middle"
                            style={{ backgroundColor: categoryColor }}
                        />{' '}
                        {categoryName}?
                    </DialogTitle>
                    <DialogDescription asChild>
                        <div className="space-y-2">
                            <p className="text-muted-foreground font-medium">
                                &ldquo;{description}&rdquo;
                            </p>
                            <p>
                                This will update all {matchCount} transactions
                                with this description, including any previously
                                categorized.
                            </p>
                        </div>
                    </DialogDescription>
                </DialogHeader>
                <DialogFooter>
                    <Button
                        variant="secondary"
                        onClick={onApplyOne}
                        disabled={isLoading}
                    >
                        Just this one
                    </Button>
                    <Button onClick={onApplyAll} disabled={isLoading}>
                        All {matchCount} matching
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    );
}
