import { fileURLToPath, URL } from 'node:url'

import { defineConfig } from 'vite'
import vue from '@vitejs/plugin-vue'
import tailwindcss from '@tailwindcss/vite'

const backend = process.env.VITE_BACKEND_URL ?? 'http://localhost:8000'
const proxied = ['/api', '/sanctum', '/auth', '/payment', '/storage', '/sitemap.xml', '/robots.txt']

// https://vite.dev/config/
export default defineConfig({
  plugins: [vue(), tailwindcss()],
  resolve: {
    alias: {
      '@': fileURLToPath(new URL('./src', import.meta.url)),
      // Page copy shared with the Laravel API (markdown versions of pages, llms.txt).
      '@content': fileURLToPath(new URL('../api/resources/content', import.meta.url)),
    },
  },
  server: {
    proxy: Object.fromEntries(proxied.map((path) => [path, { target: backend, changeOrigin: false }])),
  },
  build: {
    rollupOptions: {
      output: {
        manualChunks: {
          chart: ['chart.js', 'vue-chartjs'],
        },
      },
    },
  },
})
