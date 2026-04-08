import { defineConfig, loadEnv } from 'vite';
import laravel from 'laravel-vite-plugin';
import vue2 from '@vitejs/plugin-vue2';

/** 与浏览器地址栏一致（含主机名与端口）。127.0.0.1 与 localhost 视作不同源，勿混用。 */
function devPublicOriginFromAppUrl(appUrl) {
    const fallback = 'http://localhost:8080';
    const raw = appUrl?.trim() || fallback;
    try {
        const u = new URL(raw);
        const host = u.hostname;
        const port =
            u.port || (u.protocol === 'https:' ? '443' : '8080');
        const scheme = u.protocol === 'https:' ? 'https' : 'http';

        return {
            origin: `${scheme}://${host}:${port}`,
            hmrHost: host,
            hmrClientPort: Number(port),
            hmrProtocol: scheme === 'https' ? 'wss' : 'ws',
        };
    } catch {
        const u = new URL(fallback);

        return {
            origin: fallback,
            hmrHost: u.hostname,
            hmrClientPort: Number(u.port || '8080'),
            hmrProtocol: 'ws',
        };
    }
}

export default defineConfig(({ mode }) => {
    const env = loadEnv(mode, process.cwd(), '');
    const { origin, hmrHost, hmrClientPort, hmrProtocol } =
        devPublicOriginFromAppUrl(env.APP_URL);

    return {
        server: {
            host: '0.0.0.0',
            port: 5180,
            strictPort: true,
            origin,
            watch: {
                ignored: [
                    '**/vendor/**',
                    '**/storage/**',
                    '**/bootstrap/cache/**',
                    '**/public/build/**',
                ],
            },
            hmr: {
                protocol: hmrProtocol,
                host: hmrHost,
                clientPort: hmrClientPort,
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
    };
});
