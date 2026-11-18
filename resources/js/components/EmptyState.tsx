import { UploadIcon } from 'lucide-react';

type Props = {
    onImportClick: () => void;
    disabled?: boolean;
};

export default function EmptyState({ onImportClick, disabled }: Props) {
    return (
        <button
            type="button"
            onClick={onImportClick}
            disabled={disabled}
            className="w-full rounded-panel glass p-8 text-center transition-colors hover:bg-white/10 disabled:opacity-50"
        >
            <span className="mx-auto mb-3 flex size-12 items-center justify-center rounded-full bg-white/[0.07] shadow-[inset_0_0_0_1px_rgba(255,255,255,0.08)]">
                <UploadIcon className="size-5 text-white/80" />
            </span>
            <p className="text-sm font-medium text-white">
                Import your first CSV
            </p>
            <p className="mt-1 text-xs text-text-tertiary">
                Drag & drop or tap to select a Banque Populaire or Revolut
                export
            </p>
        </button>
    );
}
