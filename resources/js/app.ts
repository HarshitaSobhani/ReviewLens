import '../css/app.css';

import { createInertiaApp } from '@inertiajs/vue3';
import type { DefineComponent } from 'vue';
import { createApp, h } from 'vue';

const pages = import.meta.glob<DefineComponent>('./pages/**/*.vue', { eager: true });

createInertiaApp({
    title: (title) => (title ? `${title} · ReviewLens` : 'ReviewLens'),
    resolve: (name) => {
        const page = pages[`./pages/${name}.vue`];
        if (!page) throw new Error(`Unknown page: ${name}`);
        return page;
    },
    setup({ el, App, props, plugin }) {
        createApp({ render: () => h(App, props) }).use(plugin).mount(el);
    },
    progress: { color: '#4f46e5' },
});
