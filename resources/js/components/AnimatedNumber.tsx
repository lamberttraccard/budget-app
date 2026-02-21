import { motion, useMotionValue, useTransform, animate, useReducedMotion } from 'motion/react';
import { useEffect, useRef } from 'react';
import { formatAbsAmount } from '@/lib/formatAmount';

interface AnimatedNumberProps {
    value: number;
    formatter?: (cents: number) => string;
    className?: string;
    style?: React.CSSProperties;
}

export function AnimatedNumber({
    value,
    formatter = formatAbsAmount,
    className,
    style,
}: AnimatedNumberProps) {
    const shouldReduceMotion = useReducedMotion();
    const motionValue = useMotionValue(value);
    const display = useTransform(motionValue, (v) => formatter(Math.round(v)));
    const isInitialMount = useRef(true);

    useEffect(() => {
        if (isInitialMount.current) {
            isInitialMount.current = false;
            motionValue.set(value);
            return;
        }

        if (shouldReduceMotion) {
            motionValue.set(value);
            return;
        }

        const controls = animate(motionValue, value, {
            duration: 0.4,
            ease: 'easeOut',
        });

        return () => controls.stop();
    }, [value, shouldReduceMotion, motionValue]);

    return (
        <motion.span className={className} style={style}>
            {display}
        </motion.span>
    );
}
