import { render, screen } from '@testing-library/react';
import type { ReactNode } from 'react';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import BottomDock from '../BottomDock';

let mockUrl = '/';

vi.mock('@inertiajs/react', () => ({
    usePage: () => ({ url: mockUrl }),
    Link: ({ href, children, ...props }: { href: string; children: ReactNode; [key: string]: unknown }) => (
        <a href={href} {...props}>{children}</a>
    ),
}));

describe('BottomDock', () => {
    beforeEach(() => {
        mockUrl = '/';
    });

    it('renders 3 dock items with correct labels', () => {
        render(<BottomDock />);
        expect(screen.getByText('Dashboard')).toBeInTheDocument();
        expect(screen.getByText('Recurring')).toBeInTheDocument();
        expect(screen.getByText('Search')).toBeInTheDocument();
    });

    it('highlights Dashboard when on /', () => {
        mockUrl = '/';
        render(<BottomDock />);
        const dashboardLink = screen.getByText('Dashboard').closest('a');
        expect(dashboardLink).toHaveAttribute('aria-current', 'page');
        expect(dashboardLink).toHaveClass('text-white');
    });

    it('highlights Dashboard when on /categories/5 (drill-down)', () => {
        mockUrl = '/categories/5';
        render(<BottomDock />);
        const dashboardLink = screen.getByText('Dashboard').closest('a');
        expect(dashboardLink).toHaveAttribute('aria-current', 'page');
    });

    it('highlights Recurring when on /recurring', () => {
        mockUrl = '/recurring';
        render(<BottomDock />);
        const recurringLink = screen.getByText('Recurring').closest('a');
        expect(recurringLink).toHaveAttribute('aria-current', 'page');
        expect(recurringLink).toHaveClass('text-white');
    });

    it('highlights Search when on /search', () => {
        mockUrl = '/search';
        render(<BottomDock />);
        const searchLink = screen.getByText('Search').closest('a');
        expect(searchLink).toHaveAttribute('aria-current', 'page');
        expect(searchLink).toHaveClass('text-white');
    });

    it('appends accounts query param to links when selectedAccountIds is set', () => {
        render(<BottomDock selectedAccountIds={[1, 2]} />);
        const recurringLink = screen.getByText('Recurring').closest('a');
        const searchLink = screen.getByText('Search').closest('a');
        expect(recurringLink).toHaveAttribute('href', '/recurring?accounts=1,2');
        expect(searchLink).toHaveAttribute('href', '/search?accounts=1,2');
    });

    it('does not append accounts param to Dashboard link', () => {
        render(<BottomDock selectedAccountIds={[1]} />);
        const dashboardLink = screen.getByText('Dashboard').closest('a');
        expect(dashboardLink).toHaveAttribute('href', '/');
    });

    it('renders items as links with correct hrefs', () => {
        render(<BottomDock />);
        const dashboardLink = screen.getByText('Dashboard').closest('a');
        const recurringLink = screen.getByText('Recurring').closest('a');
        const searchLink = screen.getByText('Search').closest('a');
        expect(dashboardLink).toHaveAttribute('href', '/');
        expect(recurringLink).toHaveAttribute('href', '/recurring');
        expect(searchLink).toHaveAttribute('href', '/search');
    });

    it('sets aria-current="page" only on active item', () => {
        mockUrl = '/recurring';
        render(<BottomDock />);
        const dashboardLink = screen.getByText('Dashboard').closest('a');
        const recurringLink = screen.getByText('Recurring').closest('a');
        const searchLink = screen.getByText('Search').closest('a');
        expect(dashboardLink).not.toHaveAttribute('aria-current');
        expect(recurringLink).toHaveAttribute('aria-current', 'page');
        expect(searchLink).not.toHaveAttribute('aria-current');
    });

    it('is wrapped in a nav element with proper aria-label', () => {
        render(<BottomDock />);
        const nav = screen.getByRole('navigation', { name: 'Bottom navigation' });
        expect(nav).toBeInTheDocument();
    });

    it('has lg:hidden class for desktop hiding', () => {
        render(<BottomDock />);
        const nav = screen.getByRole('navigation', { name: 'Bottom navigation' });
        expect(nav).toHaveClass('lg:hidden');
    });
});
