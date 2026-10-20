import { expect, test } from '@playwright/test'
import { signInAs } from './support/session.ts'

test.describe('Orders', () => {
  test('a Sales User creates a Customer inside the Order form, confirms the Order, then cancels it', async ({
    page,
    isMobile,
  }) => {
    // Desktop and phone runs use different accounts and Customers, so parallel runs never meet.
    await signInAs(page, isMobile ? 'Admin' : 'Sales')
    const stamp = Date.now().toString(36).toUpperCase().slice(-6)
    const name = `Rana E2E ${isMobile ? 'M' : 'D'}-${stamp}`

    await page.goto('/orders/new')
    await expect(page.getByRole('heading', { name: 'New Order', level: 1 })).toBeVisible()

    // A new Customer, with a first address in Beirut, made without leaving the form.
    await page.getByRole('button', { name: 'New Customer' }).click()
    let dialog = page.getByRole('dialog', { name: 'New Customer' })
    await dialog.getByLabel('Name').fill(name)
    await dialog.getByLabel('Phone').fill(`+961 70 ${stamp}`)
    await dialog.getByRole('button', { name: 'Add Customer' }).click()
    await expect(dialog).toBeHidden()
    await page.getByRole('button', { name: 'Add an address' }).click()
    dialog = page.getByRole('dialog', { name: 'New address' })
    await dialog.getByLabel('Label').fill('Home')
    await dialog.getByLabel('Recipient').fill(name)
    await dialog.getByLabel('Country').selectOption('LB')
    await dialog.getByLabel('City').fill('Beirut')
    await dialog.getByLabel('Address line 1').fill('Hamra Street 12')
    await dialog.getByRole('button', { name: 'Add address' }).click()
    await expect(dialog).toBeHidden()

    // One Leather Weekender Bag ($149, VAT 5%) shipped by the cheapest method.
    await page
      .getByRole('searchbox', { name: 'Find a Variant by SKU or Product' })
      .fill('weekender')
    await page.getByRole('button', { name: 'Add LEATHER-WEEKENDER-BAG' }).click()
    await expect(page.getByLabel('Fulfilling warehouse')).not.toHaveValue('')
    await expect(page.getByLabel('Shipping method')).not.toHaveValue('')
    await expect(page.getByTestId('order-total')).toContainText('$')
    await page.getByRole('button', { name: 'Create Order' }).click()

    await expect(page.getByRole('heading', { name: /^ORD-\d{4}-\d{6}$/, level: 1 })).toBeVisible()
    await expect(page.getByText('Created as Pending')).toBeVisible()

    await page.getByRole('button', { name: 'Confirm Order' }).click()
    await expect(page.getByText('Moved to Confirmed')).toBeVisible()

    // Cancelling releases the reservation, so repeated runs never use the stock up.
    await page.getByRole('button', { name: 'Cancel Order' }).click()
    const confirmCancel = page.getByRole('alertdialog')
    await confirmCancel.getByLabel('Reason (optional)').fill('End-to-end test')
    await confirmCancel.getByRole('button', { name: 'Cancel Order' }).click()
    await expect(page.getByText('Moved to Cancelled')).toBeVisible()

    // The Customer's profile lists the Order.
    await page.getByRole('link', { name }).click()
    await expect(page.getByRole('heading', { name, level: 1 })).toBeVisible()
    await expect(page.getByText('Order history')).toBeVisible()
    await expect(page.getByText(/^ORD-\d{4}-\d{6}$/)).toBeVisible()
  })
})
