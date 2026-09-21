import { createInertiaApp, type ResolvedComponent } from '@inertiajs/react';
import { createRoot } from 'react-dom/client';
import '../css/tahfidz.css';

void createInertiaApp({
    title: (title) => `${title} · Penilaian Tahfidz`,
    resolve: (name) => {
        const pages = import.meta.glob<ResolvedComponent>('./Pages/**/*.tsx');
        return pages[`./Pages/${name}.tsx`]();
    },
    setup({ el, App, props }) {
        createRoot(el).render(<App {...props} />);
    },
});
