import { expect, test } from '@playwright/test'
import { signInAs } from './support/session.ts'

test('a Sales User adds a tagged Customer with addresses and a note, finds them, then deletes them', async ({
  page,
  isMobile,
}) => {
  // Desktop and phone runs use different accounts and data, so parallel runs never meet.
  await signInAs(page, isMobile ? 'Support' : 'Sales')
  const run = `${isMobile ? 'M' : 'D'}${Date.now().toString(36)}`
  const name = `Rana Haddad ${run}`
  const tag = `vip-${run}`
  const phone = `+961 70 ${Date.now().toString().slice(-6)}`

  await page.goto('/customers')
  await expect(page.getByRole('heading', { name: 'Customers', level: 1 })).toBeVisible()

  // Create: an email or a phone is required; Tags are made on the fly.
  await page.getByRole('button', { name: 'New Customer' }).first().click()
  let dialog = page.getByRole('dialog', { name: 'New Customer' })
  await dialog.getByLabel('Name').fill(name)
  await dialog.getByRole('button', { name: 'Add Customer' }).click()
  await expect(dialog.getByText('Enter an email or a phone number').first()).toBeVisible()
  await dialog.getByLabel('Phone').fill(phone)
  await dialog.getByLabel('Tags').fill(tag)
  await dialog.getByLabel('Tags').press('Enter')
  await dialog.getByRole('button', { name: 'Add Customer' }).click()

  // The profile opens; the first address becomes both defaults.
  await expect(page.getByRole('heading', { name, level: 1 })).toBeVisible()
  await expect(page.getByText(tag)).toBeVisible()
  await page.getByRole('button', { name: 'Add address' }).click()
  dialog = page.getByRole('dialog', { name: 'New address' })
  await dialog.getByLabel('Label').fill('Home')
  await dialog.getByLabel('Recipient').fill(name)
  await dialog.getByLabel('Country').selectOption('LB')
  await dialog.getByLabel('City').fill('Beirut')
  await dialog.getByLabel('Address line 1').fill('Hamra Street 12')
  await dialog.getByRole('button', { name: 'Add address' }).click()
  const home = page.getByRole('article', { name: 'Home' })
  await expect(home.getByText('Default billing')).toBeVisible()
  await expect(home.getByText('Default shipping')).toBeVisible()

  // A second address takes over shipping only.
  await page.getByRole('button', { name: 'Add address' }).click()
  dialog = page.getByRole('dialog', { name: 'New address' })
  await dialog.getByLabel('Label').fill('Office')
  await dialog.getByLabel('Recipient').fill(name)
  await dialog.getByLabel('Country').selectOption('AE')
  await dialog.getByLabel('City').fill('Dubai')
  await dialog.getByLabel('Address line 1').fill('Marina Walk 3')
  await dialog.getByLabel('Use as the default shipping address').check()
  await dialog.getByRole('button', { name: 'Add address' }).click()
  await expect(
    page.getByRole('article', { name: 'Office' }).getByText('Default shipping'),
  ).toBeVisible()
  await expect(home.getByText('Default shipping')).toBeHidden()
  await expect(home.getByText('Default billing')).toBeVisible()

  // A note is signed and timestamped.
  await page.getByLabel('New note').fill('Prefers delivery after 5 pm.')
  await page.getByRole('button', { name: 'Add note' }).click()
  await expect(
    page.getByRole('list', { name: 'Notes' }).getByText('Prefers delivery after 5 pm.'),
  ).toBeVisible()

  // The list finds the Customer by phone and by Tag.
  await page.goto('/customers')
  await page.getByRole('searchbox', { name: 'Search by name, email or phone' }).fill(phone)
  await expect(page.getByRole('link', { name })).toBeVisible()
  await page.getByRole('button', { name: 'Clear filters' }).click()
  await page.getByRole('combobox', { name: 'Tag' }).selectOption({ label: tag })
  await expect(page.getByRole('link', { name })).toBeVisible()

  // Delete (a Customer without Orders).
  await page.getByRole('link', { name }).click()
  await page.getByRole('button', { name: 'Delete' }).click()
  await page.getByRole('alertdialog').getByRole('button', { name: 'Delete' }).click()
  await expect(page.getByRole('heading', { name: 'Customers', level: 1 })).toBeVisible()
  await expect(page.getByText('Customer deleted.')).toBeVisible()
})
