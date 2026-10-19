import { expect, type Page, test } from '@playwright/test'

async function signOut(page: Page) {
  await page.getByRole('button', { name: 'Sign out' }).click()
  await expect(page.getByRole('heading', { name: 'Welcome back' })).toBeVisible()
  await expect(page).toHaveURL(/\/sign-in$/)
}

test.describe('Staff sign in with their Role', () => {
  test('an Admin signs in with the demo shortcut, sees their Role, and signs out', async ({
    page,
  }) => {
    await page.goto('/sign-in')

    await page.getByRole('button', { name: 'Sign in as Admin' }).click()

    await expect(page.getByRole('heading', { name: 'Hello, Ahmed Ali' })).toBeVisible()
    await expect(page.getByTestId('current-role')).toHaveText('Admin')

    await signOut(page)
  })

  test('a Viewer signs in with the form, sees their Role, and signs out', async ({ page }) => {
    await page.goto('/sign-in')

    await page.getByLabel('Email', { exact: true }).fill('viewer@inkcode.test')
    await page.getByLabel('Password', { exact: true }).fill('password')
    await page.getByRole('button', { name: 'Sign in', exact: true }).click()

    await expect(page.getByRole('heading', { name: 'Hello, Youssef Nasser' })).toBeVisible()
    await expect(page.getByTestId('current-role')).toHaveText('Viewer')

    await signOut(page)
  })

  test('a wrong password is refused with a message under the email field', async ({ page }) => {
    await page.goto('/sign-in')

    await page.getByLabel('Email', { exact: true }).fill('viewer@inkcode.test')
    await page.getByLabel('Password', { exact: true }).fill('not-the-password')
    await page.getByRole('button', { name: 'Sign in', exact: true }).click()

    await expect(page.getByText('These credentials do not match our records.')).toBeVisible()
    await expect(page).toHaveURL(/\/sign-in$/)
  })

  test('a signed-out visitor is sent to sign-in and returned to the page they asked for', async ({
    page,
  }) => {
    await page.goto('/orders')

    await expect(page).toHaveURL(/\/sign-in\?redirect=%2Forders$/)
    await page.getByRole('button', { name: 'Sign in as Sales' }).click()

    await expect(page).toHaveURL(/\/orders$/)
  })

  test('the session survives a page reload until the User signs out', async ({ page }) => {
    await page.goto('/sign-in')
    await page.getByRole('button', { name: 'Sign in as Finance' }).click()
    await expect(page.getByTestId('current-role')).toHaveText('Finance')

    await page.reload()
    await expect(page.getByTestId('current-role')).toHaveText('Finance')

    await signOut(page)
    await page.goto('/')
    await expect(page).toHaveURL(/\/sign-in$/)
  })

  test('the interface switches to Arabic, right to left', async ({ page }) => {
    await page.goto('/sign-in')

    await page.getByRole('button', { name: 'Switch to العربية' }).click()

    await expect(page.getByRole('heading', { name: 'مرحباً بعودتك' })).toBeVisible()
    await expect(page.locator('html')).toHaveAttribute('dir', 'rtl')
  })
})
