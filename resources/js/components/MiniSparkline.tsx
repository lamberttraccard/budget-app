import { useId } from 'react';

type Props = {
    values: number[];
    color: string;
};

const VIEW_W = 100;
const VIEW_H = 20;
const PAD_Y = 3;

export default function MiniSparkline({ values, color }: Props) {
    const gradientId = useId();

    if (values.length < 2) return null;

    const min = Math.min(...values);
    const max = Math.max(...values);
    const range = max - min;
    const yPad = range > 0 ? range * 0.1 : 1;
    const yMin = min - yPad;
    const yMax = max + yPad;

    const points = values.map((v, i) => ({
        x: (i / (values.length - 1)) * VIEW_W,
        y: PAD_Y + (1 - (v - yMin) / (yMax - yMin)) * (VIEW_H - PAD_Y * 2),
    }));

    // Catmull-Rom to cubic bezier
    let linePath = `M ${points[0].x},${points[0].y}`;
    for (let i = 0; i < points.length - 1; i++) {
        const p0 = points[Math.max(i - 1, 0)];
        const p1 = points[i];
        const p2 = points[i + 1];
        const p3 = points[Math.min(i + 2, points.length - 1)];
        const cp1x = p1.x + (p2.x - p0.x) / 4;
        const cp1y = p1.y + (p2.y - p0.y) / 4;
        const cp2x = p2.x - (p3.x - p1.x) / 4;
        const cp2y = p2.y - (p3.y - p1.y) / 4;
        linePath += ` C ${cp1x},${cp1y} ${cp2x},${cp2y} ${p2.x},${p2.y}`;
    }

    const fillPath = `${linePath} L ${points[points.length - 1].x},${VIEW_H} L ${points[0].x},${VIEW_H} Z`;

    return (
        <div className="pointer-events-none absolute inset-x-0 bottom-0" style={{ height: 20 }}>
            <svg
                viewBox={`0 0 ${VIEW_W} ${VIEW_H}`}
                preserveAspectRatio="none"
                className="block h-full w-full"
            >
                <defs>
                    <linearGradient id={gradientId} x1="0" y1="0" x2="0" y2="1">
                        <stop offset="0%" stopColor={color} stopOpacity={0.08} />
                        <stop offset="100%" stopColor={color} stopOpacity={0} />
                    </linearGradient>
                </defs>
                <path d={fillPath} fill={`url(#${gradientId})`} />
                <path
                    d={linePath}
                    fill="none"
                    stroke={color}
                    strokeWidth={1.5}
                    strokeOpacity={0.2}
                    strokeLinecap="round"
                    vectorEffect="non-scaling-stroke"
                />
            </svg>
        </div>
    );
}
