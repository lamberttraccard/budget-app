import { fireEvent, render, screen } from '@testing-library/react';
import { describe, expect, it, vi } from 'vitest';
import RecurringMerchantCard from '../RecurringMerchantCard';

const defaultProps = {
    merchantName: 'Netflix',
    categoryId: 3,
    categoryName: 'Entertainment',
    categoryColor: '#F97316',
    typicalAmountCents: -1599,
    frequency: 'Monthly',
    lastSeenMonth: '2026-02',
    monthCount: 4,
    amountVaries: false,
    isActive: true,
    minAmountCents: -1599,
    maxAmountCents: -1599,
    transactions: [
        { id: 1, date: '2026-02-15', amountCents: -1599, accountName: 'Personal' },
        { id: 2, date: '2026-01-15', amountCents: -1599, accountName: 'Personal' },
    ],
    isExpanded: false,
    onToggle: vi.fn(),
};

describe('RecurringMerchantCard', () => {
    it('renders merchant name and formatted amount', () => {
        render(<RecurringMerchantCard {...defaultProps} />);
        expect(screen.getByText('Netflix')).toBeInTheDocument();
        // formatAbsAmount(-1599) renders in French locale EUR
        expect(screen.getByText(/15,99/)).toBeInTheDocument();
    });

    it('renders frequency and last seen month', () => {
        render(<RecurringMerchantCard {...defaultProps} />);
        expect(screen.getByText('Monthly')).toBeInTheDocument();
        expect(screen.getByText('Feb 2026')).toBeInTheDocument();
    });

    it('calls onToggle when clicked', () => {
        const onToggle = vi.fn();
        render(<RecurringMerchantCard {...defaultProps} onToggle={onToggle} />);

        const button = screen.getByRole('button');
        fireEvent.click(button);

        expect(onToggle).toHaveBeenCalledTimes(1);
    });

    it('shows amount range when amountVaries is true', () => {
        render(
            <RecurringMerchantCard
                {...defaultProps}
                amountVaries={true}
                minAmountCents={-6200}
                maxAmountCents={-4500}
            />,
        );
        // Range should display low to high: 45,00 € – 62,00 €
        const rangeEl = document.querySelector('.text-\\[10px\\].text-text-secondary');
        expect(rangeEl).toBeInTheDocument();
        expect(rangeEl?.textContent).toMatch(/45,00.*–.*62,00/);
    });

    it('hides range when amountVaries is false', () => {
        const { container } = render(<RecurringMerchantCard {...defaultProps} amountVaries={false} />);
        // The range indicator text should not be present (only one amount shown)
        const rangeElements = container.querySelectorAll('.text-\\[10px\\].text-text-secondary');
        expect(rangeElements.length).toBe(0);
    });
});
