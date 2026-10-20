import { expect, test } from '@playwright/test'
import { signInAs } from './support/session.ts'

test('demo scenario 1: a 3 × 4 Product, one Variant edited, then found by filtering', async ({
  page,
  isMobile,
}) => {
  // Desktop and phone runs use different accounts and names, so parallel runs never meet.
  await signInAs(page, isMobile ? 'Admin' : 'Operations')
  const stamp = `${isMobile ? 'M' : 'D'}${Date.now().toString(36)}`.toUpperCase()
  const name = `Scenario Tee ${stamp}`

  await page.goto('/catalog/products/new')
  await page.getByLabel('Name').fill(name)
  await page.getByLabel('Category').selectOption({ label: 'Clothing › Shirts' })
  await page.getByLabel('Status').selectOption('active')
  await page.getByLabel('Base price').fill('19.90')
  await page.getByRole('button', { name: 'Create Product' }).click()
  await expect(page).toHaveURL(/\/catalog\/products\/\d+$/)
  await expect(page.getByRole('heading', { name, level: 1 })).toBeVisible()

  for (const value of ['Black', 'White', 'Red', 'S', 'M', 'L', 'XL']) {
    await page.getByRole('button', { name: value, exact: true }).click()
  }
  await page.getByRole('button', { name: 'Generate 12 Variants' }).click()
  await expect(page.getByText('12 Variants generated.')).toBeVisible()
  await expect(page.getByRole('textbox', { name: /^SKU / })).toHaveCount(12)

  const sku = `E2E-${stamp}`
  await page.getByRole('textbox', { name: /^SKU / }).first().fill(sku)
  await page
    .getByRole('textbox', { name: /^Price / })
    .first()
    .fill('29.00')
  await page.getByRole('button', { name: 'Save Variants' }).click()
  await expect(page.getByText('Variants saved.')).toBeVisible()

  await page.goto(`/catalog/products?search=${sku}&filter[status]=active&filter[price_min]=25`)
  const row = page.getByRole('row', { name: new RegExp(name) })
  await expect(row).toContainText('12 Variants')
  await expect(row).toContainText('$19.90 – $29.00')

  // Tidy up, so reruns start from the same catalog.
  await page.getByRole('button', { name: `Actions for ${name}` }).click()
  await page.getByRole('menuitem', { name: 'Delete' }).click()
  await page.getByRole('alertdialog').getByRole('button', { name: 'Delete' }).click()
  await expect(page.getByText('Product deleted.')).toBeVisible()
})
