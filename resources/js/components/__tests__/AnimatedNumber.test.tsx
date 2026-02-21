import { render, screen } from '@testing-library/react';
import { describe, it, expect, vi, beforeEach } from 'vitest';
import { formatAbsAmount, formatAmount } from '@/lib/formatAmount';

let reducedMotion = false;
const animateSpy = vi.fn((..._args: any[]) => ({ stop: () => {} }));

vi.mock('motion/react', async () => {
    const React = await import('react');
    return {
        useReducedMotion: () => reducedMotion,
        useMotionValue: (initial: number) => ({
            current: initial,
            get() { return this.current; },
            set(v: number) { this.current = v; },
        }),
        useTransform: (mv: { get: () => number }, fn: (v: number) => string) => fn(mv.get()),
        animate: (...args: any[]) => animateSpy(...args),
        motion: {
            span: React.forwardRef(
                (props: Record<string, unknown>, ref: React.Ref<HTMLSpanElement>) =>
                    React.createElement('span', { ...props, ref, 'data-testid': 'animated-number' }),
            ),
        },
    };
});

const { AnimatedNumber } = await import('../AnimatedNumber');

describe('AnimatedNumber', () => {
    beforeEach(() => {
        animateSpy.mockClear();
    });

    it('does not animate on initial mount (no counting from 0)', () => {
        reducedMotion = false;
        render(<AnimatedNumber value={150000} />);
        expect(animateSpy).not.toHaveBeenCalled();
    });

    it('calls animate() when value changes after mount', () => {
        reducedMotion = false;
        const { rerender } = render(<AnimatedNumber value={100000} />);
        animateSpy.mockClear();
        rerender(<AnimatedNumber value={200000} />);
        expect(animateSpy).toHaveBeenCalledTimes(1);
    });

    it('renders formatted value correctly for a positive amount', () => {
        reducedMotion = false;
        render(<AnimatedNumber value={150000} />);
        const el = screen.getByTestId('animated-number');
        expect(el.textContent).toBe(formatAbsAmount(150000));
    });

    it('renders correctly for zero cents', () => {
        reducedMotion = false;
        render(<AnimatedNumber value={0} />);
        const el = screen.getByTestId('animated-number');
        expect(el.textContent).toBe(formatAbsAmount(0));
    });

    it('renders correctly for negative cent values', () => {
        reducedMotion = false;
        render(<AnimatedNumber value={-42350} />);
        const el = screen.getByTestId('animated-number');
        expect(el.textContent).toBe(formatAbsAmount(-42350));
    });

    it('renders correctly with a custom formatter', () => {
        reducedMotion = false;
        render(<AnimatedNumber value={-25000} formatter={formatAmount} />);
        const el = screen.getByTestId('animated-number');
        expect(el.textContent).toBe(formatAmount(-25000));
    });

    it('applies className prop to the span', () => {
        reducedMotion = false;
        render(<AnimatedNumber value={1000} className="text-2xl font-semibold tabular-nums" />);
        expect(screen.getByTestId('animated-number')).toHaveClass('text-2xl', 'font-semibold', 'tabular-nums');
    });

    it('applies style prop to the span', () => {
        reducedMotion = false;
        render(<AnimatedNumber value={1000} style={{ color: 'rgb(255, 87, 51)' }} />);
        expect(screen.getByTestId('animated-number')).toHaveStyle({ color: 'rgb(255, 87, 51)' });
    });

    it('renders final value immediately when reduced motion is enabled', () => {
        reducedMotion = true;
        render(<AnimatedNumber value={99999} />);
        const el = screen.getByTestId('animated-number');
        expect(el.textContent).toBe(formatAbsAmount(99999));
    });
});
