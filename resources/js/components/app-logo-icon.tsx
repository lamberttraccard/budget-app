import type { SVGAttributes } from 'react';

export default function AppLogoIcon(props: SVGAttributes<SVGElement>) {
    return (
        <svg {...props} viewBox="0 0 512 512" xmlns="http://www.w3.org/2000/svg">
            <rect x="0" y="0" width="512" height="512" rx="120" ry="120" fill="#003B5C" />
            <g transform="translate(64, 64)">
                <path
                    d="M72,196 v60 a80,80 0 0,0 80,80 h80 a80,80 0 0,0 80,-80 v-60"
                    fill="none"
                    stroke="#4DB6AC"
                    strokeWidth="56"
                    strokeLinecap="round"
                    strokeLinejoin="round"
                />
                <line x1="152" y1="276" x2="152" y2="156" fill="none" stroke="#4DB6AC" strokeWidth="56" strokeLinecap="round" />
                <line x1="232" y1="276" x2="232" y2="96" fill="none" stroke="#FFB300" strokeWidth="56" strokeLinecap="round" />
                <circle cx="232" cy="96" r="28" fill="#FFB300" />
            </g>
        </svg>
    );
}
