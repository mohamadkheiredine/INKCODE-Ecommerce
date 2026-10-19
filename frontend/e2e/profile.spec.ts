import { expect, test } from '@playwright/test'
import { resetDemoAccounts, signInAs, signOut } from './support/session.ts'

test.describe.configure({ mode: 'serial' })
test.afterAll(resetDemoAccounts)

test.describe('Changing the password', () => {
  // One account, changed once: the journey is the same on every screen size.
  test.skip(({ isMobile }) => isMobile, 'Runs once, on desktop.')

  test('a wrong current password is refused under its field', async ({ page }) => {
    await signInAs(page, 'Support')
    await page.goto('/profile')

    await page.getByLabel('Current password').fill('not-my-password')
    await page.getByLabel('New password', { exact: true }).fill('Brand-new-Pass-42')
    await page.getByLabel('Confirm new password').fill('Brand-new-Pass-42')
    await page.getByRole('button', { name: 'Change password' }).click()

    await expect(page.getByText('The password is incorrect.')).toBeVisible()
  })

  test('the new password works and the old one no longer does', async ({ page }) => {
    await signInAs(page, 'Support')
    await page.goto('/profile')

    await page.getByLabel('Current password').fill('password')
    await page.getByLabel('New password', { exact: true }).fill('Brand-new-Pass-42')
    await page.getByLabel('Confirm new password').fill('Brand-new-Pass-42')
    await page.getByRole('button', { name: 'Change password' }).click()
    await expect(page.getByText('Password changed.', { exact: false })).toBeVisible()
    await signOut(page)

    await page.getByLabel('Email', { exact: true }).fill('support@inkcode.test')
    await page.getByLabel('Password', { exact: true }).fill('password')
    await page.getByRole('button', { name: 'Sign in', exact: true }).click()
    await expect(page.getByText('These credentials do not match our records.')).toBeVisible()

    await page.getByLabel('Password', { exact: true }).fill('Brand-new-Pass-42')
    await page.getByRole('button', { name: 'Sign in', exact: true }).click()
    await expect(page.getByTestId('current-role')).toHaveText('Support')
  })
})
