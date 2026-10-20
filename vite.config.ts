import tailwindcss from '@tailwindcss/vite';
import vue from '@vitejs/plugin-vue';
import laravel from 'laravel-vite-plugin';
import { defineConfig } from 'vitest/config';

export default defineConfig({
    plugins: [
        laravel({ input: ['resources/css/app.css', 'resources/js/app.ts'], refresh: true }),
        vue({ template: { transformAssetUrls: { base: null, includeAbsolute: false } } }),
        tailwindcss(),
    ],
    resolve: { alias: { '@': '/resources/js' } },
    server: { watch: { ignored: ['**/storage/framework/views/**'] } },
    test: { environment: 'jsdom', include: ['resources/js/**/*.test.ts'], globals: true },
});
