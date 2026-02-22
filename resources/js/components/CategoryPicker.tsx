import type { RefObject } from 'react';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import {
    Sheet,
    SheetContent,
    SheetHeader,
    SheetTitle,
} from '@/components/ui/sheet';
import { useIsMobile } from '@/hooks/use-mobile';
import { CategoryIcon } from '@/lib/category-icons';
import type { CategoryOption } from '@/types';

type Props = {
    categories: CategoryOption[];
    currentCategoryId: number;
    isOpen: boolean;
    onClose: () => void;
    onSelect: (categoryId: number) => void;
    triggerRef?: RefObject<HTMLSpanElement | null>;
};

function CategoryItem({ category, isCurrent, onSelect }: {
    category: CategoryOption;
    isCurrent: boolean;
    onSelect: (categoryId: number) => void;
}) {
    return (
        <button
            type="button"
            onClick={() => !isCurrent && onSelect(category.id)}
            disabled={isCurrent}
            className={`flex items-center gap-3 w-full px-4 py-3 min-h-[44px] rounded-lg transition-colors text-left ${
                isCurrent
                    ? 'opacity-40 cursor-default'
                    : 'hover:bg-white/5 cursor-pointer'
            }`}
        >
            <span
                className="size-7 rounded-lg shrink-0 flex items-center justify-center"
                style={{ backgroundColor: category.color }}
            >
                <CategoryIcon name={category.icon} className="size-4 text-black/60" />
            </span>
            <span className="text-sm text-white flex-1">{category.name}</span>
            {isCurrent && (
                <span className="text-xs text-text-tertiary">(current)</span>
            )}
        </button>
    );
}

export default function CategoryPicker({
    categories,
    currentCategoryId,
    isOpen,
    onClose,
    onSelect,
    triggerRef,
}: Props) {
    const isMobile = useIsMobile();

    if (isMobile) {
        return (
            <Sheet open={isOpen} onOpenChange={(open) => !open && onClose()}>
                <SheetContent
                    side="bottom"
                    className="rounded-t-2xl border-t-white/10"
                    style={{
                        backgroundColor: 'rgba(28, 28, 30, 0.75)',
                        backdropFilter: 'blur(40px)',
                        WebkitBackdropFilter: 'blur(40px)',
                    }}
                >
                    <SheetHeader>
                        <SheetTitle className="text-white text-base">
                            Choose Category
                        </SheetTitle>
                    </SheetHeader>
                    <div className="max-h-[60vh] overflow-y-auto py-2">
                        {categories.map((cat) => (
                            <CategoryItem
                                key={cat.id}
                                category={cat}
                                isCurrent={cat.id === currentCategoryId}
                                onSelect={onSelect}
                            />
                        ))}
                    </div>
                </SheetContent>
            </Sheet>
        );
    }

    return (
        <DropdownMenu open={isOpen} onOpenChange={(open) => !open && onClose()}>
            <DropdownMenuTrigger asChild>
                <span
                    ref={triggerRef}
                    className="fixed pointer-events-none opacity-0"
                    aria-hidden="true"
                />
            </DropdownMenuTrigger>
            <DropdownMenuContent
                align="end"
                className="w-64 max-h-[400px] overflow-y-auto border-white/10"
                style={{
                    backgroundColor: 'rgba(28, 28, 30, 0.75)',
                    backdropFilter: 'blur(40px)',
                    WebkitBackdropFilter: 'blur(40px)',
                }}
            >
                {categories.map((cat) => {
                    const isCurrent = cat.id === currentCategoryId;
                    return (
                        <DropdownMenuItem
                            key={cat.id}
                            onClick={() => !isCurrent && onSelect(cat.id)}
                            disabled={isCurrent}
                            className={`flex items-center gap-3 min-h-[36px] ${
                                isCurrent ? 'opacity-40' : ''
                            }`}
                        >
                            <span
                                className="size-6 rounded-md shrink-0 flex items-center justify-center"
                                style={{ backgroundColor: cat.color }}
                            >
                                <CategoryIcon name={cat.icon} className="size-3.5 text-black/60" />
                            </span>
                            <span className="flex-1">{cat.name}</span>
                            {isCurrent && (
                                <span className="text-xs text-text-tertiary">(current)</span>
                            )}
                        </DropdownMenuItem>
                    );
                })}
            </DropdownMenuContent>
        </DropdownMenu>
    );
}
