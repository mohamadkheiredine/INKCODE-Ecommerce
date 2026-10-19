import { execFileSync } from 'node:child_process'
import path from 'node:path'
import { expect, type Page } from '@playwright/test'

/** Signs in with the demo shortcut for a Role and waits for the landing page. */
export async function signInAs(page: Page, role: string) {
  await page.goto('/sign-in')
  await page.getByRole('button', { name: `Sign in as ${role}` }).click()
  await expect(page.getByTestId('current-role')).toBeVisible()
}

/** Signs out from the header's user menu, in whichever language the page is in. */
export async function signOut(page: Page) {
  await page.getByRole('button', { name: /Open the user menu|فتح قائمة المستخدم/ }).click()
  await page.getByRole('menuitem', { name: /Sign out|تسجيل الخروج/ }).click()
  await expect(page).toHaveURL(/\/sign-in$/)
}

/**
 * Puts every demo account back to its documented password, English and light. Journeys
 * that change an account call it when they finish, so later runs start from the same state.
 */
export function resetDemoAccounts() {
  execFileSync('php', ['artisan', 'db:seed', '--class=DemoUsersSeeder', '--force'], {
    cwd: path.resolve(import.meta.dirname, '../../../backend'),
    stdio: 'ignore',
  })
}
