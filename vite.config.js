import { defineConfig } from 'vite';
import vue from '@vitejs/plugin-vue';

export default defineConfig({
    plugins: [vue()],
    build: {
        outDir: 'public/build',
        manifest: true,
        rollupOptions: {
            input: {
                main: 'assets/js/app.js',
            },
        },
    },
    server: {
        watch: {
            usePolling: true,
        },
        hmr: {
            overlay: true,
        },
    },
    // test: {
    //     globals: true,
    //     environment: 'jsdom',
    //     setupFiles: ['assets/js/tests/vitest.setup.js'],
    // },
    publicDir: false,
});
