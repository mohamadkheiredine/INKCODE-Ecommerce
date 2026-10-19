import { expect, test } from '@playwright/test'
import { resetDemoAccounts, signInAs } from './support/session.ts'

// These journeys read demo accounts' welcome notifications (and one switches a language),
// so they run one at a time and put the demo accounts back afterwards.
test.describe.configure({ mode: 'serial' })
test.afterAll(resetDemoAccounts)

test.describe('The notifications bell', () => {
  test('a User opens their welcome in Arabic and it is marked as read', async ({
    page,
    isMobile,
  }) => {
    // Desktop and phone runs use different accounts so they never share notifications.
    const [role, name] = isMobile ? ['Finance', 'Nadia Farouk'] : ['Sales', 'Lina Saleh']
    await signInAs(page, role)
    await expect(page.getByRole('button', { name: 'Notifications, 1 unread' })).toBeVisible()

    await page.getByRole('button', { name: 'Switch to العربية' }).click()
    await page.getByRole('button', { name: 'الإشعارات، 1 غير مقروء' }).click()
    const panel = page.getByRole('dialog', { name: 'الإشعارات' })
    await panel.getByRole('link', { name: `أهلاً بك في إنككود كوميرس يا ${name}` }).click()

    await expect(page).toHaveURL(/\/profile$/)
    await expect(panel).toBeHidden()
    await expect(page.getByRole('button', { name: 'الإشعارات', exact: true })).toBeVisible()
    await page.reload()
    await expect(page.getByRole('button', { name: 'الإشعارات', exact: true })).toBeVisible()
  })

  test('a User marks all their notifications as read at once', async ({ page, isMobile }) => {
    await signInAs(page, isMobile ? 'Viewer' : 'Admin')

    await page.getByRole('button', { name: 'Notifications, 1 unread' }).click()
    const panel = page.getByRole('dialog', { name: 'Notifications' })
    await expect(panel.getByText('Welcome to Inkcode Commerce')).toBeVisible()
    await panel.getByRole('button', { name: 'Mark all as read' }).click()

    await expect(page.getByText('All notifications marked as read.')).toBeVisible()
    await expect(panel.getByRole('button', { name: 'Mark all as read' })).toBeDisabled()
    await expect(panel.getByRole('button', { name: 'Mark as read' })).toHaveCount(0)
    await page.reload()
    await expect(page.getByRole('button', { name: 'Notifications', exact: true })).toBeVisible()
  })
})
