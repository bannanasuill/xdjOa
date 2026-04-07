import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import vue2 from '@vitejs/plugin-vue2';

export default defineConfig({
    server: {
        host: '0.0.0.0',
        port: 5180,
        strictPort: true,
        origin: 'http://localhost:8080',
        watch: {
            ignored: [
                '**/vendor/**',
                '**/storage/**',
                '**/bootstrap/cache/**',
                '**/public/build/**',
            ],
        },
        hmr: {
            protocol: 'ws',
            host: 'localhost',
            clientPort: 8080,
            path: '/@vite/ws',
        },
    },
    plugins: [
        vue2(),
        laravel({
            input: [
                'resources/css/app.css',
                'resources/js/app.js',
                'resources/js/admin/main.js',
            ],
            refresh: false,
        }),
    ],
});
