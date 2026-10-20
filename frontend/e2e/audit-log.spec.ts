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

test.afterAll(resetDemoSettings)

test('the Admin changes the store name, then finds that change in the audit log with its values', async ({
  page,
  isMobile,
}) => {
  test.skip(isMobile, 'Runs once, on desktop.')
  const name = `Audit Store ${Date.now()}`

  await signInAs(page, 'Admin')
  await page.goto('/settings')
  const previous = await page.getByLabel('Store name').inputValue()
  await page.getByLabel('Store name').fill(name)
  await page.getByRole('button', { name: 'Save changes' }).click()
  await expect(page.getByText('Settings saved.')).toBeVisible()

  await page.getByRole('link', { name: 'Audit log' }).click()
  await page.getByRole('combobox', { name: 'Record type' }).selectOption('Settings')
  await page.getByRole('searchbox', { name: 'Search records' }).fill(name)
  await expect(page).toHaveURL(/filter%5Brecord_type%5D=store_settings/)

  const row = page.getByRole('row').filter({ hasText: name })
  await expect(row).toHaveCount(1)
  await expect(row).toContainText('Updated')
  await expect(row).toContainText('Ahmed Ali')
  await row.getByRole('button', { name: `View changes to ${name}` }).click()

  const changes = page.getByRole('dialog').getByRole('row', { name: /Name/ })
  await expect(changes).toContainText(previous)
  await expect(changes).toContainText(name)
})
