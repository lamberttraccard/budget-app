type Props = {
    visible: boolean;
};

export default function ImportDropZone({ visible }: Props) {
    if (!visible) return null;

    return (
        <div className="pointer-events-none fixed inset-0 z-40 flex items-center justify-center bg-background/80 backdrop-blur-sm">
            <div className="rounded-2xl border-2 border-dashed border-zinc-500 px-12 py-10 text-center">
                <p className="text-lg font-semibold text-white">Drop CSV to import</p>
                <p className="mt-1 text-sm text-text-secondary">
                    Banque Populaire or Revolut export
                </p>
            </div>
        </div>
    );
}
