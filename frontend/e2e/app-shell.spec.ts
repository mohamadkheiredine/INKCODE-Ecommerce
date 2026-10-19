import { expect, type Page, test } from '@playwright/test'
import { resetDemoAccounts, signInAs, signOut } from './support/session.ts'

// These journeys change demo accounts, so they run one at a time and tidy up afterwards.
test.describe.configure({ mode: 'serial' })
test.afterAll(resetDemoAccounts)

const preferenceSaved = (page: Page) =>
  page.waitForResponse(
    (response) => response.url().includes('/auth/me/preferences') && response.ok(),
  )

test.describe('The app shell', () => {
  test('language and theme follow the User to their next sign-in on another device', async ({
    page,
    browser,
    isMobile,
  }) => {
    // Desktop and phone runs use different accounts so they never share preferences.
    const role = isMobile ? 'Inventory' : 'Operations'
    await signInAs(page, role)

    let saved = preferenceSaved(page)
    await page.getByRole('button', { name: 'Switch to العربية' }).click()
    await saved
    await expect(page.locator('html')).toHaveAttribute('dir', 'rtl')
    await expect(page.getByRole('navigation', { name: 'مسار التنقل' })).toBeVisible()

    saved = preferenceSaved(page)
    await page.getByRole('button', { name: 'التبديل إلى المظهر الداكن' }).click()
    await saved
    await expect(page.locator('html')).toHaveClass(/dark/)

    if (!isMobile) {
      // The sidebar moves to the reading side: the right in Arabic.
      const sidebar = await page.locator('[data-slot=sidebar-container]').boundingBox()
      const width = page.viewportSize()?.width ?? 0
      expect(sidebar?.x ?? 0).toBeGreaterThan(width / 2)
    }
    await signOut(page)

    // Another device: a fresh browser with nothing remembered locally.
    const otherDevice = await browser.newContext()
    const otherPage = await otherDevice.newPage()
    await otherPage.goto('/sign-in')
    await expect(otherPage.locator('html')).toHaveAttribute('dir', 'ltr')
    await otherPage.getByRole('button', { name: `Sign in as ${role}` }).click()

    await expect(otherPage.getByTestId('current-role')).toBeVisible()
    await expect(otherPage.locator('html')).toHaveAttribute('dir', 'rtl')
    await expect(otherPage.locator('html')).toHaveClass(/dark/)
    await expect(otherPage.getByRole('heading', { level: 1 })).toContainText('أهلاً')
    await otherDevice.close()
  })

  test('the sidebar becomes a drawer on phones', async ({ page, isMobile }) => {
    test.skip(!isMobile, 'The drawer is the phone layout.')
    await signInAs(page, 'Viewer')
    await page.getByRole('button', { name: 'Open the user menu' }).click()
    await page.getByRole('menuitem', { name: 'Profile' }).click()
    await expect(page.getByRole('heading', { name: 'Profile', level: 1 })).toBeVisible()

    await page.getByRole('button', { name: 'Toggle sidebar' }).click()
    const drawer = page.getByRole('dialog')
    await expect(drawer).toBeVisible()
    await drawer.getByRole('link', { name: 'Home' }).click()

    await expect(drawer).toBeHidden()
    await expect(page).toHaveURL(/\/$/)
  })

  test('breadcrumbs lead back up from the profile', async ({ page }) => {
    await signInAs(page, 'Viewer')
    await page.goto('/profile')

    const breadcrumbs = page.getByRole('navigation', { name: 'Breadcrumb' })
    await expect(breadcrumbs.getByText('Profile')).toHaveAttribute('aria-current', 'page')
    await breadcrumbs.getByRole('link', { name: 'Home' }).click()

    await expect(page).toHaveURL(/\/$/)
    await expect(page).toHaveTitle('Home · Inkcode Commerce')
  })
})
