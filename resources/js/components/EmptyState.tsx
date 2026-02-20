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
            className="w-full rounded-xl border-2 border-dashed border-zinc-700 bg-surface p-8 text-center transition-colors hover:border-zinc-500 hover:bg-surface-elevated disabled:opacity-50"
        >
            <UploadIcon className="mx-auto mb-3 size-8 text-text-tertiary" />
            <p className="text-sm font-medium text-white">Import your first CSV</p>
            <p className="mt-1 text-xs text-text-tertiary">
                Drag & drop or tap to select a Banque Populaire or Revolut export
            </p>
        </button>
    );
}
