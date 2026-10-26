import { defineConfig } from 'vite';
import vue from '@vitejs/plugin-vue';

export default defineConfig({
  plugins: [vue()],
  // base relatif agar hasil build bisa dibuka dari file/preview statis
  base: './',
  server: {
    port: 5173,
    // Teruskan /api ke backend Laravel saat dev agar bebas CORS
    proxy: {
      '/api': {
        target: 'http://localhost:8000',
        changeOrigin: true
      }
    }
  }
});
