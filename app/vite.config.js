import { defineConfig } from 'vite';
import vue from '@vitejs/plugin-vue';
import { fileURLToPath } from 'node:url';

// Builds the member area into ../assets/app with a manifest, so PHP can enqueue hashed files.
export default defineConfig({
  plugins: [vue()],
  base: './',
  build: {
    outDir: fileURLToPath(new URL('../assets/app', import.meta.url)),
    emptyOutDir: true,
    manifest: 'manifest.json',
    rollupOptions: { input: fileURLToPath(new URL('./src/main.js', import.meta.url)) },
  },
});
