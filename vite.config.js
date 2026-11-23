import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import tailwindcss from '@tailwindcss/vite';

export default defineConfig({
    plugins: [
        laravel({
            // site.css and app.css are the Scarlet site's two stylesheets and load in that order on every page.
            input: [
                'resources/css/site.css',
                'resources/css/app.css',
                'resources/css/game.css',
                'resources/css/class.css',
                'resources/css/landing.css',
                'resources/css/tailwind.css',
                'resources/js/app.js',
                'resources/js/sc.js',
                'resources/js/landing.js',
                'resources/js/demo.js',
                'resources/js/map/design.js',
                'resources/js/scene.js',
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
