import path from 'node:path'
import tailwindcss from '@tailwindcss/vite'
import react from '@vitejs/plugin-react'
import { defineConfig } from 'vite'
import { devServers } from './dev-servers.ts'

// The SPA talks to the API on its own origin: in development Vite forwards these
// paths to Laravel; a production host must do the same, or set VITE_API_URL (docs/adr/0002).
const { spaPort, apiUrl } = devServers()
const apiPaths = ['/api', '/sanctum', '/docs', '/storage']

// https://vite.dev/config/
export default defineConfig({
  plugins: [react(), tailwindcss()],
  resolve: {
    alias: {
      '@': path.resolve(import.meta.dirname, './src'),
    },
  },
  server: {
    port: spaPort,
    strictPort: true,
    proxy: Object.fromEntries(apiPaths.map((p) => [p, { target: apiUrl, changeOrigin: false }])),
  },
})
