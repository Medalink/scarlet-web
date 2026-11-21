import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import tailwindcss from '@tailwindcss/vite';

export default defineConfig({
    plugins: [
        laravel({
            // site.css is the Scarlet site's stylesheet. tailwind.css is for utility-styled fragments and is loaded by no page yet.
            input: [
                'resources/css/site.css',
                'resources/css/landing.css',
                'resources/css/tailwind.css',
                'resources/js/landing.js',
                'resources/js/demo.js',
            ],
            refresh: true,
        }),
        tailwindcss(),
    ],
    server: {
        host: '0.0.0.0',
        watch: {
            ignored: ['**/storage/framework/views/**'],
        },
    },
});
