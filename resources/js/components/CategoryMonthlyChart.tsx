import { router } from '@inertiajs/react';
import { motion, useReducedMotion } from 'motion/react';
import { useCallback, useEffect, useId, useMemo, useState } from 'react';

import type { MonthlyTotal } from '@/types';

type Props = {
    monthlyTotals: MonthlyTotal[];
    currentMonth: string;
    accentColor: string;
    categoryId: number;
    selectedAccountIds: number[];
    chartMonth: string;
};

const PADDING = { top: 14, right: 16, bottom: 20, left: 16 };
const CHART_HEIGHT = 56;
const SVG_WIDTH_DESKTOP = 800;
const SVG_WIDTH_MOBILE = 400;

function formatMonthLabel(month: string): string {
    const [year, m] = month.split('-');
    const date = new Date(Number(year), Number(m) - 1);
    return date.toLocaleDateString('en-US', { month: 'short' });
}

export default function CategoryMonthlyChart({
    monthlyTotals,
    currentMonth,
    accentColor,
    categoryId,
    selectedAccountIds,
    chartMonth,
}: Props) {
    const gradientId = useId();
    const shouldReduceMotion = useReducedMotion();
    const [svgWidth, setSvgWidth] = useState(SVG_WIDTH_DESKTOP);

    useEffect(() => {
        if (typeof window.matchMedia !== 'function') return;
        const mq = window.matchMedia('(max-width: 1024px)');
        const update = () => setSvgWidth(mq.matches ? SVG_WIDTH_MOBILE : SVG_WIDTH_DESKTOP);
        update();
        mq.addEventListener('change', update);
        return () => mq.removeEventListener('change', update);
    }, []);
    const chartWidth = svgWidth - PADDING.left - PADDING.right;
    const svgHeight = CHART_HEIGHT + PADDING.top + PADDING.bottom;

    const points = useMemo(() => {
        if (monthlyTotals.length === 0) return [];
        const absValues = monthlyTotals.map((d) => Math.abs(d.amountCents));
        const min = Math.min(...absValues);
        const max = Math.max(...absValues);
        const range = max - min;
        const yPadding = range > 0 ? range * 0.1 : 1;
        const yMin = min - yPadding;
        const yMax = max + yPadding;

        return monthlyTotals.map((d, i) => {
            const x =
                monthlyTotals.length === 1
                    ? PADDING.left + chartWidth / 2
                    : PADDING.left + (i / (monthlyTotals.length - 1)) * chartWidth;
            const absVal = Math.abs(d.amountCents);
            const y =
                range === 0
                    ? PADDING.top + CHART_HEIGHT / 2
                    : PADDING.top + (1 - (absVal - yMin) / (yMax - yMin)) * CHART_HEIGHT;
            return { x, y, month: d.month, amountCents: d.amountCents };
        });
    }, [monthlyTotals, chartWidth]);

    const handlePointClick = useCallback(
        (index: number) => {
            const month = monthlyTotals[index].month;
            if (month === currentMonth) return;
            router.get(
                `/categories/${categoryId}`,
                { month, accounts: selectedAccountIds.length > 0 ? selectedAccountIds.join(',') : undefined, chart_month: chartMonth },
                {
                    preserveState: true,
                    preserveScroll: true,
                    only: ['category', 'subcategories', 'totalAmountCents', 'transactionCount', 'currentMonth', 'selectedAccountIds', 'monthlyTotals', 'chartMonth', 'transactions'],
                },
            );
        },
        [monthlyTotals, currentMonth, categoryId, selectedAccountIds, chartMonth],
    );

    const handleKeyDown = useCallback(
        (e: React.KeyboardEvent, index: number) => {
            if (e.key === 'Enter' || e.key === ' ') {
                e.preventDefault();
                handlePointClick(index);
            }
        },
        [handlePointClick],
    );

    if (monthlyTotals.length < 2) {
        return null;
    }

    // Catmull-Rom to cubic bezier for smooth curves through all points
    const linePath = (() => {
        if (points.length < 2) return '';
        let d = `M ${points[0].x},${points[0].y}`;
        for (let i = 0; i < points.length - 1; i++) {
            const p0 = points[Math.max(i - 1, 0)];
            const p1 = points[i];
            const p2 = points[i + 1];
            const p3 = points[Math.min(i + 2, points.length - 1)];
            const cp1x = p1.x + (p2.x - p0.x) / 4;
            const cp1y = p1.y + (p2.y - p0.y) / 4;
            const cp2x = p2.x - (p3.x - p1.x) / 4;
            const cp2y = p2.y - (p3.y - p1.y) / 4;
            d += ` C ${cp1x},${cp1y} ${cp2x},${cp2y} ${p2.x},${p2.y}`;
        }
        return d;
    })();

    const fillPath = `${linePath} L ${points[points.length - 1].x},${PADDING.top + CHART_HEIGHT} L ${points[0].x},${PADDING.top + CHART_HEIGHT} Z`;

    const showEveryOther = monthlyTotals.length > 6;

    return (
        <motion.div
            initial={shouldReduceMotion ? false : { opacity: 0 }}
            animate={{ opacity: 1 }}
            transition={{ duration: 0.3, ease: 'easeOut' }}
        >
            <div className="rounded-xl bg-surface/80 backdrop-blur-2xl p-4">
                <svg
                    viewBox={`0 0 ${svgWidth} ${svgHeight}`}
                    className="w-full"
                    style={{ height: 'auto' }}
                    role="img"
                    aria-label="Monthly spending trend"
                >
                    <title>Monthly spending trend for this category</title>
                    <defs>
                        <linearGradient id={gradientId} x1="0" y1="0" x2="0" y2="1">
                            <stop offset="0%" stopColor={accentColor} stopOpacity={0.15} />
                            <stop offset="100%" stopColor={accentColor} stopOpacity={0} />
                        </linearGradient>
                    </defs>

                    {/* Gradient fill area */}
                    <path
                        d={fillPath}
                        fill={`url(#${gradientId})`}
                    />

                    {/* Line */}
                    <path
                        d={linePath}
                        fill="none"
                        stroke={accentColor}
                        strokeWidth={2.5}
                        strokeLinecap="round"
                    />

                    {/* Data points */}
                    {points.map((p, i) => {
                        const isCurrent = p.month === currentMonth;
                        return (
                            <g key={p.month}>
                                {/* Soft glow */}
                                {isCurrent && (
                                    <circle
                                        cx={p.x}
                                        cy={p.y}
                                        r={11}
                                        fill={accentColor}
                                        opacity={0.25}
                                    />
                                )}
                                {/* Visible dot */}
                                <circle
                                    cx={p.x}
                                    cy={p.y}
                                    r={isCurrent ? 4 : 3}
                                    fill="var(--color-surface, #1a1a2e)"
                                    stroke={accentColor}
                                    strokeWidth={isCurrent ? 2 : 1.5}
                                />
                                {/* Tap target */}
                                <circle
                                    cx={p.x}
                                    cy={p.y}
                                    r={12}
                                    fill="transparent"
                                    style={{ outline: 'none' }}
                                    className="cursor-pointer"
                                    tabIndex={0}
                                    role="button"
                                    aria-label={`${formatMonthLabel(p.month)}`}
                                    onClick={() => handlePointClick(i)}
                                    onKeyDown={(e) => handleKeyDown(e, i)}
                                />
                            </g>
                        );
                    })}

                    {/* X-axis labels */}
                    {points.map((p, i) => {
                        if (showEveryOther && i % 2 !== 0 && i !== points.length - 1 && p.month !== currentMonth) {
                            return null;
                        }
                        return (
                            <text
                                key={`label-${p.month}`}
                                x={p.x}
                                y={svgHeight - 2}
                                textAnchor="middle"
                                fill="rgba(255,255,255,0.4)"
                                fontSize={11}
                                fontWeight={p.month === currentMonth ? 600 : 400}
                            >
                                {formatMonthLabel(p.month)}
                            </text>
                        );
                    })}
                </svg>
            </div>
        </motion.div>
    );
}
