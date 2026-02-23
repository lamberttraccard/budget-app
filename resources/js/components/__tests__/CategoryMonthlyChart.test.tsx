import { fireEvent, render, screen } from '@testing-library/react';
import { describe, expect, it, vi } from 'vitest';

import type { MonthlyTotal } from '@/types';

vi.mock('@inertiajs/react', () => ({
    router: { get: vi.fn() },
}));

vi.mock('motion/react', async () => {
    const React = await import('react');
    return {
        motion: {
            div: React.forwardRef(
                (props: Record<string, unknown>, ref: React.Ref<HTMLDivElement>) =>
                    React.createElement('div', { ...props, ref }),
            ),
        },
        useReducedMotion: () => false,
    };
});

const { default: CategoryMonthlyChart } = await import('../CategoryMonthlyChart');

const defaultProps = {
    currentMonth: '2026-02',
    accentColor: '#3B82F6',
    categoryId: 1,
    selectedAccountIds: [1],
    chartMonth: '2026-02',
};

function makeTotals(count: number): MonthlyTotal[] {
    return Array.from({ length: count }, (_, i) => ({
        month: `2026-${String(i + 1).padStart(2, '0')}`,
        amountCents: -(i + 1) * 1000,
    }));
}

describe('CategoryMonthlyChart', () => {
    it('renders nothing when monthlyTotals has fewer than 2 entries', () => {
        const { container } = render(
            <CategoryMonthlyChart monthlyTotals={makeTotals(1)} {...defaultProps} />,
        );
        expect(container.innerHTML).toBe('');
    });

    it('renders nothing when monthlyTotals is empty', () => {
        const { container } = render(
            <CategoryMonthlyChart monthlyTotals={[]} {...defaultProps} />,
        );
        expect(container.innerHTML).toBe('');
    });

    it('renders SVG when monthlyTotals has 2+ entries', () => {
        render(
            <CategoryMonthlyChart monthlyTotals={makeTotals(3)} {...defaultProps} />,
        );
        const svg = screen.getByRole('img', { name: /monthly spending trend/i });
        expect(svg).toBeInTheDocument();
    });

    it('renders correct number of interactive data points', () => {
        render(
            <CategoryMonthlyChart monthlyTotals={makeTotals(4)} {...defaultProps} />,
        );
        const buttons = screen.getAllByRole('button');
        expect(buttons).toHaveLength(4);
    });

    it('has aria-labels on data points with month name', () => {
        render(
            <CategoryMonthlyChart monthlyTotals={makeTotals(2)} {...defaultProps} />,
        );
        const buttons = screen.getAllByRole('button');
        expect(buttons[0]).toHaveAttribute('aria-label', expect.stringContaining('Jan'));
        expect(buttons[1]).toHaveAttribute('aria-label', expect.stringContaining('Feb'));
    });

    it('calls router.get when clicking a non-current month point', async () => {
        const { router } = await import('@inertiajs/react');
        vi.mocked(router.get).mockClear();

        render(
            <CategoryMonthlyChart
                monthlyTotals={makeTotals(3)}
                {...defaultProps}
                currentMonth="2026-03"
            />,
        );

        const buttons = screen.getAllByRole('button');
        fireEvent.click(buttons[0]);

        expect(router.get).toHaveBeenCalledWith(
            '/categories/1',
            { month: '2026-01', accounts: '1', chart_month: '2026-02' },
            expect.objectContaining({
                preserveState: true,
                preserveScroll: true,
                only: expect.arrayContaining(['monthlyTotals', 'chartMonth', 'transactions']),
            }),
        );
    });

    it('does not navigate when clicking current month point', async () => {
        const { router } = await import('@inertiajs/react');
        vi.mocked(router.get).mockClear();

        render(
            <CategoryMonthlyChart
                monthlyTotals={makeTotals(3)}
                {...defaultProps}
                currentMonth="2026-02"
            />,
        );

        const buttons = screen.getAllByRole('button');
        fireEvent.click(buttons[1]);

        expect(router.get).not.toHaveBeenCalled();
    });

    it('navigates via Enter keydown', async () => {
        const { router } = await import('@inertiajs/react');
        vi.mocked(router.get).mockClear();

        render(
            <CategoryMonthlyChart
                monthlyTotals={makeTotals(3)}
                {...defaultProps}
                currentMonth="2026-03"
            />,
        );

        const buttons = screen.getAllByRole('button');
        fireEvent.keyDown(buttons[0], { key: 'Enter' });

        expect(router.get).toHaveBeenCalledWith(
            '/categories/1',
            { month: '2026-01', accounts: '1', chart_month: '2026-02' },
            expect.objectContaining({ preserveState: true }),
        );
    });
});
