import { render } from '@testing-library/react';
import { describe, expect, it } from 'vitest';

import MiniSparkline from '../MiniSparkline';

describe('MiniSparkline', () => {
    it('renders nothing with fewer than 2 values', () => {
        const { container } = render(
            <MiniSparkline values={[100]} color="#10B981" />,
        );
        expect(container.innerHTML).toBe('');
    });

    it('renders nothing with empty values', () => {
        const { container } = render(
            <MiniSparkline values={[]} color="#10B981" />,
        );
        expect(container.innerHTML).toBe('');
    });

    it('renders an SVG with 2+ values', () => {
        const { container } = render(
            <MiniSparkline values={[100, 200, 150]} color="#10B981" />,
        );
        const svg = container.querySelector('svg');
        expect(svg).toBeInTheDocument();
    });

    it('has pointer-events none for non-interactive behavior', () => {
        const { container } = render(
            <MiniSparkline values={[100, 200, 150]} color="#10B981" />,
        );
        const wrapper = container.firstElementChild as HTMLElement;
        expect(wrapper).toHaveClass('pointer-events-none');
    });

    it('renders a path element for the line', () => {
        const { container } = render(
            <MiniSparkline values={[100, 200, 150]} color="#10B981" />,
        );
        const paths = container.querySelectorAll('path');
        // Should have at least 2 paths: fill + line
        expect(paths.length).toBeGreaterThanOrEqual(2);
    });

    it('uses the provided color for stroke', () => {
        const { container } = render(
            <MiniSparkline values={[100, 200]} color="#FB7185" />,
        );
        const paths = container.querySelectorAll('path');
        const linePath = Array.from(paths).find((p) => p.getAttribute('stroke'));
        expect(linePath?.getAttribute('stroke')).toBe('#FB7185');
    });
});
