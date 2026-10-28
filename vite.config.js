import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import tailwindcss from '@tailwindcss/vite';

export default defineConfig({
    plugins: [
        laravel({
            input: ['resources/css/app.css', 'resources/js/app.js', 'resources/css/cabinet.css', 'resources/css/home.css', 'resources/js/home.js', 'resources/css/game-l2.css', 'resources/js/game-l2.js', 'resources/css/game-gta.css', 'resources/js/game-gta.js', 'resources/css/game-cs.css', 'resources/js/game-cs.js', 'resources/css/game-mc.css', 'resources/js/game-mc.js', 'resources/css/game-dayz.css', 'resources/js/game-dayz.js'],
            refresh: true,
        }),
        tailwindcss(),
    ],
});
