import { type Page, expect, test } from '@playwright/test'
import { signInAs } from './support/session.ts'

async function signInWith(page: Page, email: string, password: string) {
  await page.goto('/sign-in')
  await page.getByLabel('Email', { exact: true }).fill(email)
  await page.getByLabel('Password', { exact: true }).fill(password)
  await page.getByRole('button', { name: 'Sign in', exact: true }).click()
}

test('the Admin adds a User who signs in, then deactivates them and their session ends', async ({
  browser,
  page,
  isMobile,
}) => {
  const run = `${isMobile ? 'm' : 'd'}${Date.now().toString(36)}`
  const name = `Lina Saleh ${run}`
  const email = `lina.${run}@inkcode.test`
  const password = 'Temporary-pass-1'

  // The Admin adds a Finance User with a temporary password.
  await signInAs(page, 'Admin')
  await page.goto('/users')
  await expect(page.getByRole('heading', { name: 'Users', level: 1 })).toBeVisible()
  await page.getByRole('button', { name: 'New User' }).first().click()
  const dialog = page.getByRole('dialog', { name: 'New User' })
  await dialog.getByLabel('Name').fill(name)
  await dialog.getByLabel('Email').fill(email)
  await dialog.getByLabel('Role').selectOption('finance')
  await dialog.getByLabel('Temporary password').fill(password)
  await dialog.getByLabel('Confirm password').fill(password)
  await dialog.getByRole('button', { name: 'Add User' }).click()
  await expect(dialog).toBeHidden()

  // The new User signs in from their own browser with the temporary password.
  const theirs = await browser.newContext()
  const userPage = await theirs.newPage()
  await signInWith(userPage, email, password)
  await expect(userPage.getByTestId('current-role')).toHaveText('Finance')

  // The Admin deactivates them; their open session ends on its next request.
  await page.getByRole('searchbox', { name: 'Search Users' }).fill(run)
  await page.getByRole('button', { name: `Deactivate ${name}` }).click()
  await expect(page.getByRole('button', { name: `Reactivate ${name}` })).toBeVisible()

  await userPage.reload()
  await expect(userPage).toHaveURL(/\/sign-in/)
  await signInWith(userPage, email, password)
  await expect(
    userPage.getByText('Your access has been deactivated. Ask an Admin to reactivate it.'),
  ).toBeVisible()

  await theirs.close()
})
