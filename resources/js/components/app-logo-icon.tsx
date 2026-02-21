import type { SVGAttributes } from 'react';

export default function AppLogoIcon(props: SVGAttributes<SVGElement>) {
    return (
        <svg {...props} viewBox="0 0 512 512" xmlns="http://www.w3.org/2000/svg">
            <rect x="0" y="0" width="512" height="512" rx="115" fill="#111113" />
            <path
                d="M 176 144 L 256 144 C 326 144, 326 256, 256 256 L 176 256"
                fill="none"
                stroke="#6F59D6"
                strokeWidth="68"
                strokeLinecap="round"
                strokeLinejoin="round"
            />
            <path
                d="M 176 256 L 272 256 C 356 256, 356 380, 272 380 L 176 380"
                fill="none"
                stroke="#F2B246"
                strokeWidth="68"
                strokeLinecap="round"
                strokeLinejoin="round"
            />
            <line x1="176" y1="256" x2="236" y2="256" stroke="#D63F76" strokeWidth="68" strokeLinecap="round" />
            <line x1="176" y1="144" x2="176" y2="380" stroke="#4EC5B1" strokeWidth="68" strokeLinecap="round" />
        </svg>
    );
}
