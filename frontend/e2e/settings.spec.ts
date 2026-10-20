import { execFileSync } from 'node:child_process'
import path from 'node:path'
import { expect, test } from '@playwright/test'
import { signInAs } from './support/session.ts'

/** Puts the demo store's settings back the way the seeder made them. */
function resetDemoSettings() {
  execFileSync('php', ['artisan', 'db:seed', '--class=DemoStoreSettingsSeeder', '--force'], {
    cwd: path.resolve(import.meta.dirname, '../../backend'),
    stdio: 'ignore',
  })
}

test.describe.configure({ mode: 'serial' })
test.afterAll(resetDemoSettings)

test('the Admin changes the Store currency and VAT, and every User sees the store that way', async ({
  page,
  isMobile,
}) => {
  // One store, changed once: the journey is the same on every screen size.
  test.skip(isMobile, 'Runs once, on desktop.')

  await signInAs(page, 'Admin')
  await page.goto('/settings')
  await expect(page.getByTestId('store-badge')).toContainText('Prices in USD')

  await page.getByLabel('Store name').fill('Cedar & Co.')
  await page.getByLabel('Currency code').fill('EUR')
  await page.getByLabel('Currency symbol').fill('€')
  await page.getByLabel('VAT rate').fill('11.5')
  await page.getByRole('radio', { name: /Preparing/ }).check()
  await expect(page.getByRole('status', { name: 'How amounts will look' })).toContainText(
    '€1,234.50',
  )
  await page.getByRole('button', { name: 'Save changes' }).click()
  await expect(page.getByText('Settings saved.')).toBeVisible()

  // Saved on the server: a fresh load shows it, and the sidebar follows at once.
  await page.reload()
  await expect(page.getByLabel('VAT rate')).toHaveValue('11.50')
  await expect(page.getByRole('radio', { name: /Preparing/ })).toBeChecked()
  await expect(page.getByTestId('store-badge')).toContainText('Cedar & Co.')
  await expect(page.getByTestId('store-badge')).toContainText('Prices in EUR')

  // Another Role reads the public part but cannot open Settings.
  await page.context().clearCookies()
  await signInAs(page, 'Viewer')
  await expect(page.getByTestId('store-badge')).toContainText('Prices in EUR')
  await page.goto('/settings')
  await expect(page.getByText("You can't open this page")).toBeVisible()
})
