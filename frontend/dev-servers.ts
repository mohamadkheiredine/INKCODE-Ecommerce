import { loadEnv } from 'vite'

/**
 * Where this checkout's dev servers listen. Parallel git worktrees ("slots", see CLAUDE.md)
 * set SPA_PORT and API_PORT in their untracked .env.local (scripts/use-slot.mjs writes it);
 * the main checkout and CI use the defaults.
 */
export function devServers() {
  const env = loadEnv('development', import.meta.dirname, '')
  const spaPort = Number(env.SPA_PORT || 5173)
  const apiPort = Number(env.API_PORT || 8000)

  return {
    spaPort,
    apiPort,
    spaUrl: `http://localhost:${spaPort}`,
    apiUrl: env.API_PROXY_TARGET || `http://localhost:${apiPort}`,
  }
}
