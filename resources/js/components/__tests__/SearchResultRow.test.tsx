import { fireEvent, render, screen } from '@testing-library/react';
import { describe, it, expect, vi } from 'vitest';
import SearchResultRow from '../SearchResultRow';

const defaultProps = {
    id: 1,
    date: '2026-02-10',
    description: 'CARREFOUR CITY PARIS',
    amountCents: -5000,
    categoryId: 3,
    categoryName: 'Food',
    categoryColor: '#A3E635',
    accountName: 'Personal',
    onTap: vi.fn(),
};

describe('SearchResultRow', () => {
    it('renders merchant name and formatted amount', () => {
        render(<SearchResultRow {...defaultProps} />);
        expect(screen.getByText('CARREFOUR CITY PARIS')).toBeInTheDocument();
        // formatAbsAmount(-5000) renders in French locale EUR
        expect(screen.getByText(/50,00/)).toBeInTheDocument();
    });

    it('renders category color dot with correct background color', () => {
        const { container } = render(<SearchResultRow {...defaultProps} />);
        const dot = container.querySelector('.rounded-full.size-2');
        expect(dot).toHaveStyle({ backgroundColor: '#A3E635' });
    });

    it('renders date', () => {
        render(<SearchResultRow {...defaultProps} />);
        // formatDate('2026-02-10') renders as "Feb 10"
        expect(screen.getByText('Feb 10')).toBeInTheDocument();
    });

    it('renders category name', () => {
        render(<SearchResultRow {...defaultProps} />);
        expect(screen.getByText('Food')).toBeInTheDocument();
    });

    it('renders account name badge', () => {
        render(<SearchResultRow {...defaultProps} />);
        expect(screen.getByText('Personal')).toBeInTheDocument();
    });

    it('calls onTap with transaction id and category id on click', () => {
        const onTap = vi.fn();
        render(<SearchResultRow {...defaultProps} onTap={onTap} />);

        const button = screen.getByRole('button');
        fireEvent.click(button);

        expect(onTap).toHaveBeenCalledTimes(1);
        expect(onTap).toHaveBeenCalledWith(1, 3, expect.any(HTMLElement));
    });
});
