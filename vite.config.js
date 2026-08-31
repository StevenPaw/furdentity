import { defineConfig } from 'vite'
import vue from '@vitejs/plugin-vue'

// https://vite.dev/config/
export default defineConfig(({ command }) => {
  const ddevUrl = process.env.DDEV_PRIMARY_URL || 'https://furdentity.ddev.site'

  return {
    root: 'frontend',
    // Served from the domain root by the Vite dev server, but from
    // /frontend/ in production (see public/frontend/ + FrontendController).
    base: command === 'build' ? '/frontend/' : '/',
    plugins: [vue()],
    resolve: {
      alias: {
        '@': new URL('./frontend/src', import.meta.url).pathname,
      },
    },
    server: {
      host: '0.0.0.0',
      port: 5173,
      strictPort: true,
      origin: ddevUrl.replace(/:\d+$/, '') + ':5173',
      cors: {
        origin: /https?:\/\/([A-Za-z0-9\-.]+)?(\.ddev\.site)(?::\d+)?$/,
      },
      watch: {
        usePolling: true,
      },
      proxy: {
        '/api': {
          target: ddevUrl,
          changeOrigin: true,
          secure: false,
        },
      },
    },
    build: {
      outDir: '../public/frontend',
      emptyOutDir: true,
      manifest: false,
      sourcemap: true,
    },
  }
})
