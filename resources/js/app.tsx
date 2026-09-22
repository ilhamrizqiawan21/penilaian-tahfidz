import { createInertiaApp, type ResolvedComponent } from '@inertiajs/react';
import { createRoot } from 'react-dom/client';
import '../css/tahfidz.css';
import Workspace from './Layouts/Workspace';

void createInertiaApp({
    layout: (name) => name.startsWith('Auth/') ? null : Workspace,
    title: (title) => `${title} · Penilaian Tahfidz`,
    resolve: (name) => {
        const pages = import.meta.glob<ResolvedComponent>('./Pages/**/*.tsx');
        return pages[`./Pages/${name}.tsx`]();
    },
    setup({ el, App, props }) {
        createRoot(el).render(<App {...props} />);
    },
});
