import type { ReactNode, SVGProps } from 'react';

export type IconName = 'dashboard' | 'check' | 'book' | 'users' | 'trend' | 'checklist' | 'clipboard' | 'archive' | 'plus' | 'edit' | 'trash' | 'target';

const paths: Record<IconName, ReactNode> = {
    dashboard: <>
        <rect x="3" y="3" width="7.5" height="7.5" rx="1.5" />
        <rect x="13.5" y="3" width="7.5" height="7.5" rx="1.5" />
        <rect x="3" y="13.5" width="7.5" height="7.5" rx="1.5" />
        <rect x="13.5" y="13.5" width="7.5" height="7.5" rx="1.5" />
    </>,
    check: <>
        <rect x="4.5" y="4" width="15" height="17" rx="2.2" />
        <path d="M8.5 4V3.4A1.4 1.4 0 0 1 9.9 2h4.2a1.4 1.4 0 0 1 1.4 1.4V4" />
        <path d="m9 13 2.4 2.4L15.5 11" />
    </>,
    book: <>
        <path d="M12 6.2c-1.5-1-4-1.6-6.3-1.6-.5 0-1 .05-1.5.15v13.1c2.1-.5 5-.25 7.5 1 2.5-1.25 5.4-1.5 7.5-1V4.75c-.5-.1-1-.15-1.5-.15-2.3 0-4.8.6-6.3 1.6Z" />
        <path d="M12 6.2v13.45" />
    </>,
    users: <>
        <circle cx="9" cy="8.2" r="3" />
        <path d="M3.3 19.5c0-3.3 2.6-5.8 5.7-5.8s5.7 2.5 5.7 5.8" />
        <circle cx="17" cy="9.3" r="2.2" />
        <path d="M15.6 13.9c2.3.6 3.9 2.7 3.9 5.3" />
    </>,
    trend: <>
        <path d="M4 21V13.5" />
        <path d="M10 21V7" />
        <path d="M16 21v-9.5" />
        <path d="m20 6.5-5.2 5.5-3.4-3.2L4 16" />
    </>,
    checklist: <>
        <path d="m4 6.5 1.3 1.3L7.8 5" />
        <path d="M11 6.5h9" />
        <path d="m4 13 1.3 1.3L7.8 11.5" />
        <path d="M11 13h9" />
        <path d="m4 19.5 1.3 1.3 2.5-2.8" />
        <path d="M11 19.5h9" />
    </>,
    clipboard: <>
        <rect x="4.5" y="4" width="15" height="17" rx="2.2" />
        <path d="M8.5 4V3.4A1.4 1.4 0 0 1 9.9 2h4.2a1.4 1.4 0 0 1 1.4 1.4V4" />
        <path d="M8.2 10.5h7.6" />
        <path d="M8.2 14.2h7.6" />
        <path d="M8.2 17.9h4.8" />
    </>,
    archive: <>
        <ellipse cx="12" cy="5.3" rx="7.3" ry="2.4" />
        <path d="M4.7 5.3v6.2c0 1.3 3.3 2.4 7.3 2.4s7.3-1.1 7.3-2.4V5.3" />
        <path d="M4.7 11.5v6.2c0 1.3 3.3 2.4 7.3 2.4s7.3-1.1 7.3-2.4v-6.2" />
    </>,
    plus: <>
        <path d="M12 5v14" />
        <path d="M5 12h14" />
    </>,
    edit: <>
        <path d="M12 20h9" />
        <path d="M16.5 3.5a2.12 2.12 0 0 1 3 3L7 19l-4 1 1-4Z" />
    </>,
    trash: <>
        <path d="M3 6h18" />
        <path d="M8 6V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2" />
        <path d="M19 6l-1 14a2 2 0 0 1-2 2H8a2 2 0 0 1-2-2L5 6" />
        <path d="M10 11v6" />
        <path d="M14 11v6" />
    </>,
    target: <>
        <circle cx="12" cy="12" r="9" />
        <circle cx="12" cy="12" r="5" />
        <circle cx="12" cy="12" r="1.2" fill="currentColor" stroke="none" />
    </>,
};

export function Icon({ name, ...props }: { name: IconName } & SVGProps<SVGSVGElement>) {
    return (
        <svg viewBox="0 0 24 24" width="19" height="19" fill="none" stroke="currentColor" strokeWidth="1.7" strokeLinecap="round" strokeLinejoin="round" aria-hidden="true" {...props}>
            {paths[name]}
        </svg>
    );
}
