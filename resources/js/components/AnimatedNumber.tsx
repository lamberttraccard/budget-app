import { motion, useMotionValue, useTransform, animate, useReducedMotion } from 'motion/react';
import { useEffect, useRef } from 'react';
import { formatAbsAmount, splitDecimals } from '@/lib/formatAmount';

interface AnimatedNumberProps {
    value: number;
    formatter?: (cents: number) => string;
    className?: string;
    style?: React.CSSProperties;
    /** When set, the decimals and currency render in their own span with this class. */
    decimalsClassName?: string;
}

export function AnimatedNumber({
    value,
    formatter = formatAbsAmount,
    className,
    style,
    decimalsClassName,
}: AnimatedNumberProps) {
    const shouldReduceMotion = useReducedMotion();
    const motionValue = useMotionValue(value);
    const display = useTransform(motionValue, (v) => formatter(Math.round(v)));
    const whole = useTransform(motionValue, (v) => splitDecimals(formatter(Math.round(v)))[0]);
    const decimals = useTransform(motionValue, (v) => splitDecimals(formatter(Math.round(v)))[1]);
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
            {decimalsClassName ? (
                <>
                    <motion.span>{whole}</motion.span>
                    <motion.span className={decimalsClassName}>{decimals}</motion.span>
                </>
            ) : (
                display
            )}
        </motion.span>
    );
}
