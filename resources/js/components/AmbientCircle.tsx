import { motion, useReducedMotion, useScroll, useTransform } from 'motion/react';

export default function AmbientCircle() {
    const shouldReduceMotion = useReducedMotion();
    const { scrollY } = useScroll();
    const circleY = useTransform(scrollY, [0, 500], shouldReduceMotion ? [0, 0] : [0, 80]);
    const circleScale = useTransform(scrollY, [0, 500], shouldReduceMotion ? [1, 1] : [1, 1.08]);

    return (
        <div
            className="fixed inset-0 pointer-events-none overflow-hidden"
            aria-hidden="true"
        >
            <motion.div
                className="absolute left-1/2 -translate-x-1/2 rounded-full"
                style={{
                    width: 'max(1000px, 120vw)',
                    aspectRatio: '1',
                    top: 'calc(45vh - max(1000px, 120vw))',
                    background: 'rgba(255,255,255,0.03)',
                    y: circleY,
                    scale: circleScale,
                }}
            />
        </div>
    );
}
