import { expect, test } from '@playwright/test'
import { signInAs } from './support/session.ts'

test.describe('Inventory', () => {
  test('an Inventory User opens a Warehouse, receives stock, and sees it on Stock levels and the Product page', async ({
    page,
    isMobile,
  }) => {
    // Desktop and phone runs use different accounts and Warehouses, so parallel runs never meet.
    await signInAs(page, isMobile ? 'Admin' : 'Inventory')
    const stamp = Date.now().toString(36).toUpperCase().slice(-6)
    const code = `E2E${isMobile ? 'M' : 'D'}-${stamp}`
    const name = `Byblos ${code}`

    await page.goto('/inventory/warehouses')
    await expect(page.getByRole('heading', { name: 'Warehouses', level: 1 })).toBeVisible()
    await page.getByRole('button', { name: 'New Warehouse' }).first().click()
    const dialog = page.getByRole('dialog', { name: 'New Warehouse' })
    await dialog.getByLabel('Name').fill(name)
    await dialog.getByLabel('Code').fill(code.toLowerCase())
    await dialog.getByLabel('Address').fill('Old Souk, Byblos, Lebanon')
    await dialog.getByRole('button', { name: 'Create Warehouse' }).click()
    await expect(dialog).toBeHidden()

    // Receive 7 Leather Weekender Bags into it.
    await page.goto('/inventory/receipts/new')
    await expect(page.getByRole('heading', { name: 'Receive stock', level: 1 })).toBeVisible()
    await page.getByLabel('Warehouse').selectOption({ label: `${code} · ${name}` })
    await page.getByLabel('Supplier reference').fill(`PO-${code}`)
    await page.getByRole('searchbox', { name: 'Search a SKU or Product to add' }).fill('weekender')
    await page.getByRole('button', { name: 'Add a Variant: LEATHER-WEEKENDER-BAG' }).click()
    await page.getByLabel('Quantity').fill('7')
    await page.getByLabel('Unit cost').fill('60')
    await page.getByRole('button', { name: 'Record Receipt' }).click()
    await expect(page.getByRole('heading', { name: /^RCV-\d{4}-\d{6}$/, level: 1 })).toBeVisible()

    // Stock levels for that Warehouse show the units and their Stock value.
    await page.goto('/inventory/stock')
    await page.getByLabel('Warehouse').selectOption({ label: `${code} · ${name}` })
    const row = page.getByRole('row', { name: /Leather Weekender Bag/ })
    await expect(row).toContainText('7')
    await expect(page.getByTestId('total-on-hand')).toHaveText('7')

    // The Product page shows the Variant's stock in the new Warehouse.
    await row.getByRole('link', { name: 'Leather Weekender Bag' }).click()
    await expect(
      page.getByRole('heading', { name: 'Leather Weekender Bag', level: 1 }),
    ).toBeVisible()
    const stock = page.getByRole('table', { name: 'Stock' })
    await expect(stock.getByRole('columnheader', { name: code })).toBeVisible()
  })
})
