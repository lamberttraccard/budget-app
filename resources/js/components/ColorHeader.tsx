import { motion, useReducedMotion, useScroll, useTransform } from 'motion/react';
import { AnimatedNumber } from '@/components/AnimatedNumber';
import { CategoryIcon } from '@/lib/category-icons';

type Props = {
    name: string;
    color: string;
    icon: string | null;
    totalAmountCents: number;
    transactionCount: number;
};

/** Returns true if the hex color is light enough for dark text. */
function isLightColor(hex: string): boolean {
    const r = parseInt(hex.slice(1, 3), 16) / 255;
    const g = parseInt(hex.slice(3, 5), 16) / 255;
    const b = parseInt(hex.slice(5, 7), 16) / 255;
    const luminance =
        0.2126 * (r <= 0.03928 ? r / 12.92 : ((r + 0.055) / 1.055) ** 2.4) +
        0.7152 * (g <= 0.03928 ? g / 12.92 : ((g + 0.055) / 1.055) ** 2.4) +
        0.0722 * (b <= 0.03928 ? b / 12.92 : ((b + 0.055) / 1.055) ** 2.4);
    return luminance > 0.15;
}

export default function ColorHeader({ name, color, icon, totalAmountCents, transactionCount }: Props) {
    const light = isLightColor(color);
    const textPrimary = light ? 'text-black/90' : 'text-white/90';
    const textSecondary = light ? 'text-black/50' : 'text-white/50';

    const shouldReduceMotion = useReducedMotion();
    const { scrollY } = useScroll();
    const contentOpacity = useTransform(scrollY, [0, 250], shouldReduceMotion ? [1, 1] : [1, 0]);
    const circleY = useTransform(scrollY, [0, 500], shouldReduceMotion ? [0, 0] : [0, 80]);
    const circleScale = useTransform(scrollY, [0, 500], shouldReduceMotion ? [1, 1] : [1, 1.08]);

    return (
        <div
            className="relative -mx-4 sm:-mx-8 px-4 sm:px-8 pt-4 pb-16"
            style={{ clipPath: 'inset(-100vh -100vw -100vw -100vw)' }}
        >
            {/* Colored circle — curved background with parallax */}
            <motion.div
                className="absolute left-1/2 -translate-x-1/2 rounded-full pointer-events-none"
                style={{
                    width: 'max(1000px, 120vw)',
                    aspectRatio: '1',
                    top: 'calc(45vh - max(1000px, 120vw))',
                    background: color,
                    y: circleY,
                    scale: circleScale,
                }}
                aria-hidden="true"
            />

            {/* Soft highlight for depth */}
            <motion.div
                className="absolute left-1/2 -translate-x-1/2 rounded-full pointer-events-none"
                style={{
                    width: 'max(350px, 50%)',
                    aspectRatio: '1',
                    top: '5%',
                    background: `radial-gradient(circle, ${light ? 'rgba(255,255,255,0.15)' : 'rgba(255,255,255,0.05)'} 0%, transparent 70%)`,
                    y: circleY,
                    scale: circleScale,
                }}
                aria-hidden="true"
            />

            {/* Content — fades out as content scrolls over the header */}
            <motion.div className="relative z-10 flex flex-col items-center pt-4" style={{ opacity: contentOpacity }}>
                <div
                    className="w-20 h-20 rounded-full mb-4 flex items-center justify-center shadow-lg"
                    style={{
                        backgroundColor: light ? 'rgba(255,255,255,0.3)' : 'rgba(255,255,255,0.12)',
                        backdropFilter: 'blur(12px)',
                        WebkitBackdropFilter: 'blur(12px)',
                    }}
                >
                    <CategoryIcon name={icon} className={`size-9 ${textPrimary}`} />
                </div>

                <h1 className={`text-xl font-semibold ${textPrimary}`}>{name}</h1>

                <AnimatedNumber
                    value={totalAmountCents}
                    className={`mt-2 text-4xl font-bold tabular-nums ${textPrimary}`}
                />

                <p className={`mt-1 text-sm ${textSecondary}`}>
                    {transactionCount} transaction{transactionCount !== 1 ? 's' : ''}
                </p>
            </motion.div>
        </div>
    );
}
