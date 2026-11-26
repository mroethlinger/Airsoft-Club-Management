import { defineConfig } from 'vite';
import vue from '@vitejs/plugin-vue';
import tailwindcss from '@tailwindcss/vite';
import { fileURLToPath } from 'node:url';

// Builds the member area into ../assets/app with a manifest, so PHP can print the hashed files.
export default defineConfig({
  plugins: [vue(), tailwindcss()],
  base: './',
  build: {
    outDir: fileURLToPath(new URL('../assets/app', import.meta.url)),
    emptyOutDir: true,
    manifest: 'manifest.json',
    rollupOptions: { input: fileURLToPath(new URL('./src/main.js', import.meta.url)) },
  },
});
