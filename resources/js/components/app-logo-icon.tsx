import type { SVGAttributes } from 'react';

export default function AppLogoIcon(props: SVGAttributes<SVGElement>) {
    return (
        <svg {...props} viewBox="0 0 512 512" xmlns="http://www.w3.org/2000/svg">
            <rect x="0" y="0" width="512" height="512" rx="115" fill="#07080C" />
            <line x1="144" y1="160" x2="368" y2="160" stroke="#2DD4BF" strokeWidth="64" strokeLinecap="round" />
            <line x1="144" y1="256" x2="296" y2="256" stroke="#818CF8" strokeWidth="64" strokeLinecap="round" />
            <line x1="144" y1="352" x2="216" y2="352" stroke="#FBBF24" strokeWidth="64" strokeLinecap="round" />
            <circle cx="304" cy="352" r="32" fill="#F472B6" />
        </svg>
    );
}
