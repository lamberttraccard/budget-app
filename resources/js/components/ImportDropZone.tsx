import { AnimatePresence, motion, useReducedMotion } from 'motion/react';

type Props = {
    visible: boolean;
};

export default function ImportDropZone({ visible }: Props) {
    const shouldReduceMotion = useReducedMotion();

    return (
        <AnimatePresence>
            {visible && (
                <motion.div
                    className="pointer-events-none fixed inset-0 z-40 flex items-center justify-center bg-background/80 backdrop-blur-sm"
                    initial={shouldReduceMotion ? false : { opacity: 0 }}
                    animate={{ opacity: 1 }}
                    exit={shouldReduceMotion ? undefined : { opacity: 0 }}
                    transition={{ duration: 0.2 }}
                >
                    <motion.div
                        className="rounded-2xl border-2 border-dashed border-zinc-500 px-12 py-10 text-center"
                        initial={shouldReduceMotion ? false : { opacity: 0, scale: 0.95 }}
                        animate={{ opacity: 1, scale: 1 }}
                        exit={shouldReduceMotion ? undefined : { opacity: 0, scale: 0.95 }}
                        transition={{ duration: 0.2 }}
                    >
                        <p className="text-lg font-semibold text-white">Drop CSV to import</p>
                        <p className="mt-1 text-sm text-text-secondary">
                            Banque Populaire or Revolut export
                        </p>
                    </motion.div>
                </motion.div>
            )}
        </AnimatePresence>
    );
}
