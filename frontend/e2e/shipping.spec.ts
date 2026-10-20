import { expect, test } from '@playwright/test'
import { signInAs } from './support/session.ts'

/** A label no other run or project uses, so journeys never trip over each other's data. */
const unique = (label: string, isMobile: boolean) =>
  `${label} ${isMobile ? 'M' : 'D'}${Date.now().toString(36)}`

test.describe('Shipping zones', () => {
  test('an Operations User covers a city with its own zone and method, checks an address, then tidies up', async ({
    page,
    isMobile,
  }) => {
    // Desktop and phone runs use different accounts, so parallel runs never meet.
    await signInAs(page, isMobile ? 'Admin' : 'Operations')
    await page.goto('/shipping/zones')
    await expect(page.getByRole('heading', { name: 'Shipping zones', level: 1 })).toBeVisible()

    const zone = unique('Bekaa', isMobile)
    const city = unique('Zahle', isMobile)

    // A city in Lebanon: the demo "Lebanon" zone covers the rest of the country.
    await page.getByRole('button', { name: 'New Shipping zone' }).first().click()
    const zoneDialog = page.getByRole('dialog', { name: 'New Shipping zone' })
    await zoneDialog.getByLabel('Name').fill(zone)
    await zoneDialog.getByLabel('Country').selectOption('LB')
    await zoneDialog.getByLabel('City').fill(city)
    await zoneDialog.getByRole('button', { name: 'Create Shipping zone' }).click()

    // Creating a zone opens it, ready for its Shipping methods.
    await expect(page.getByRole('heading', { name: zone, level: 1 })).toBeVisible()
    await page.getByRole('button', { name: 'New Shipping method' }).first().click()
    const methodDialog = page.getByRole('dialog', { name: 'New Shipping method' })
    await methodDialog.getByLabel('Name').fill('Valley express')
    await methodDialog.getByLabel('Price').fill('8.25')
    await methodDialog.getByLabel('Fastest (days)').fill('1')
    await methodDialog.getByLabel('Slowest (days)').fill('1')
    await methodDialog.getByRole('button', { name: 'Create Shipping method' }).click()
    const methods = page.getByRole('table', { name: 'Shipping methods' })
    await expect(methods.getByRole('row', { name: /Valley express/ })).toContainText('$8.25')

    // The city now gets this zone; another Lebanese city still gets the country zone.
    await page.getByRole('link', { name: 'Shipping zones' }).first().click()
    const checker = page.getByRole('region', { name: 'Check an address' })
    await checker.getByLabel('Country').selectOption('LB')
    await checker.getByLabel('City').fill(city.toUpperCase())
    await checker.getByRole('button', { name: 'Check' }).click()
    await expect(checker.getByText(`Shipping zone: ${zone}`)).toBeVisible()
    await expect(checker).toContainText('Valley express')

    await checker.getByLabel('City').fill('Tyre')
    await checker.getByRole('button', { name: 'Check' }).click()
    await expect(checker.getByText('Shipping zone: Lebanon')).toBeVisible()

    await checker.getByLabel('Country').selectOption('FR')
    await checker.getByLabel('City').fill('Paris')
    await checker.getByRole('button', { name: 'Check' }).click()
    await expect(checker.getByText(/No Shipping zone covers Paris, France/)).toBeVisible()

    await page.getByRole('button', { name: `Actions for ${zone}` }).click()
    await page.getByRole('menuitem', { name: 'Delete' }).click()
    await page.getByRole('alertdialog').getByRole('button', { name: 'Delete' }).click()
    await expect(page.getByRole('link', { name: zone })).toBeHidden()
  })
})
