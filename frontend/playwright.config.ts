import { defineConfig, devices } from '@playwright/test'

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
    baseURL: process.env.E2E_BASE_URL ?? 'http://localhost:5173',
    trace: 'on-first-retry',
    screenshot: 'only-on-failure',
  },
  projects: [
    { name: 'desktop', use: { ...devices['Desktop Chrome'] } },
    { name: 'mobile', use: { ...devices['Pixel 7'] } },
  ],
  webServer: [
    {
      command: 'php artisan serve --port=8000',
      cwd: '../backend',
      url: 'http://localhost:8000/up',
      reuseExistingServer: !process.env.CI,
    },
    {
      command: 'npm run dev',
      url: 'http://localhost:5173',
      reuseExistingServer: !process.env.CI,
    },
  ],
})
