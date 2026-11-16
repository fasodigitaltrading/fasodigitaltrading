import { defineConfig } from 'vite';
export default defineConfig({
    build: {
        manifest: 'manifest.json',
        outDir: 'public/build',
        copyPublicDir: false,
        publicDir: false,
        rollupOptions: { input: 'resources/js/app.js' },
    },
});
