import { defineConfig, devices } from '@playwright/test'
import { devServers } from './dev-servers.ts'

// This checkout's own servers: a worktree slot (CLAUDE.md) moves them off 8000/5173, so its
// tests never reuse, or seed against, another checkout's API and database.
const { spaUrl, apiPort, apiUrl } = devServers()

// End-to-end tests drive the real SPA against the real Laravel API and MySQL.
// Start MySQL first (`docker compose up -d mysql`); both servers are started here when not running.
export default defineConfig({
  testDir: './e2e',
  globalSetup: './e2e/global-setup.ts',
  fullyParallel: true,
  // `php artisan serve` handles one request at a time on Windows, so run browsers one after
  // another locally; CI (Linux, PHP_CLI_SERVER_WORKERS) and the Docker stack can go parallel.
  workers: process.env.CI ? 2 : 1,
  expect: { timeout: 10_000 },
  forbidOnly: !!process.env.CI,
  retries: process.env.CI ? 2 : 0,
  reporter: [['list'], ['html', { open: 'never' }]],
  use: {
    baseURL: process.env.E2E_BASE_URL ?? spaUrl,
    trace: 'on-first-retry',
    screenshot: 'only-on-failure',
  },
  projects: [
    { name: 'desktop', use: { ...devices['Desktop Chrome'] } },
    { name: 'mobile', use: { ...devices['Pixel 7'] } },
  ],
  webServer: [
    {
      command: `php artisan serve --port=${apiPort}`,
      cwd: '../backend',
      url: `${apiUrl}/up`,
      reuseExistingServer: !process.env.CI,
    },
    {
      command: 'npm run dev',
      url: spaUrl,
      reuseExistingServer: !process.env.CI,
    },
  ],
})
