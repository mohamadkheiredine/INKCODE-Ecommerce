import { execFileSync } from 'node:child_process'
import path from 'node:path'
import { expect, type Page, test } from '@playwright/test'
import { signInAs } from './support/session.ts'

/** Puts the demo Categories and Brands back the way the seeder made them. */
function resetDemoCatalog() {
  execFileSync('php', ['artisan', 'db:seed', '--class=DemoCatalogSeeder', '--force'], {
    cwd: path.resolve(import.meta.dirname, '../../backend'),
    stdio: 'ignore',
  })
}

test.describe.configure({ mode: 'serial' })
test.afterAll(resetDemoCatalog)

/** A name no other run or project uses, so journeys never trip over each other's data. */
const unique = (label: string, isMobile: boolean) =>
  `${label} ${isMobile ? 'M' : 'D'}${Date.now().toString(36)}`

/** A 1×1 transparent PNG: the smallest valid logo. */
const PNG = Buffer.from(
  'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNkYAAAAAYAAjCB0C8AAAAASUVORK5CYII=',
  'base64',
)

async function openRowActions(page: Page, name: string) {
  await page.getByRole('button', { name: `Actions for ${name}` }).click()
}

test.describe('Categories', () => {
  test('an Operations User builds a two-level Category, cannot delete a parent with children, then tidies up', async ({
    page,
    isMobile,
  }) => {
    // Desktop and phone runs use different accounts, so parallel runs never meet.
    await signInAs(page, isMobile ? 'Admin' : 'Operations')
    await page.goto('/catalog/categories')
    await expect(page.getByRole('heading', { name: 'Categories', level: 1 })).toBeVisible()

    const parent = unique('Outerwear', isMobile)
    const child = unique('Raincoats', isMobile)

    await page.getByRole('button', { name: 'New Category' }).first().click()
    let dialog = page.getByRole('dialog', { name: 'New Category' })
    await dialog.getByLabel('Name').fill(parent)
    await dialog.getByRole('button', { name: 'Create Category' }).click()
    await expect(page.getByText('Category created.')).toBeVisible()

    await page.getByRole('button', { name: 'New Category' }).first().click()
    dialog = page.getByRole('dialog', { name: 'New Category' })
    await dialog.getByLabel('Name').fill(child)
    await dialog.getByLabel('Parent Category').selectOption({ label: parent })
    await dialog.getByRole('button', { name: 'Create Category' }).click()
    await expect(page.getByText('Category created.').first()).toBeVisible()

    // The search lives in the URL, so the address can be shared.
    await page.getByRole('searchbox', { name: 'Search Categories' }).fill(parent.split(' ')[1]!)
    await expect(page).toHaveURL(/search=/)
    const childRow = page.getByRole('row', { name: new RegExp(child) })
    await expect(childRow).toContainText(parent)

    await openRowActions(page, parent)
    await page.getByRole('menuitem', { name: 'Delete' }).click()
    await page.getByRole('alertdialog').getByRole('button', { name: 'Delete' }).click()
    await expect(
      page.getByText(`${parent} still has child Categories. Move or delete them first.`),
    ).toBeVisible()

    for (const name of [child, parent]) {
      await openRowActions(page, name)
      await page.getByRole('menuitem', { name: 'Delete' }).click()
      await page.getByRole('alertdialog').getByRole('button', { name: 'Delete' }).click()
      await expect(page.getByRole('row', { name: new RegExp(name) })).toBeHidden()
    }
  })

  test('a shared link opens the same list, and back returns to the previous one', async ({
    page,
  }) => {
    await signInAs(page, 'Viewer')
    await page.goto('/catalog/categories?filter[level]=child&sort=-name')

    await expect(page.getByRole('combobox', { name: 'Level' })).toHaveValue('child')
    await expect(page.getByRole('row', { name: /^Watches watches Accessories/ })).toBeVisible()
    await expect(page.getByRole('row', { name: /^Accessories accessories/ })).toBeHidden()
    await expect(page.getByRole('columnheader', { name: /Name/ })).toHaveAttribute(
      'aria-sort',
      'descending',
    )

    await page.getByRole('combobox', { name: 'Level' }).selectOption('top')
    await expect(
      page.getByRole('row', { name: /^Accessories accessories Top level/ }),
    ).toBeVisible()

    await page.goBack()
    await expect(page.getByRole('combobox', { name: 'Level' })).toHaveValue('child')
    await expect(page.getByRole('row', { name: /^Watches watches Accessories/ })).toBeVisible()
  })

  test('a Viewer sees the catalog, and the API refuses their attempt to change it', async ({
    page,
  }) => {
    await signInAs(page, 'Viewer')
    await page.goto('/catalog/categories')
    await expect(page.getByRole('row', { name: /^Clothing clothing Top level/ })).toBeVisible()
    await expect(page.getByRole('button', { name: 'New Category' })).toBeHidden()

    // Hiding the button is not the guard: the API refuses the request itself.
    const xsrf = (await page.context().cookies()).find(({ name }) => name === 'XSRF-TOKEN')
    const answer = await page.request.post('/api/v1/categories', {
      headers: {
        Accept: 'application/json',
        'X-XSRF-TOKEN': decodeURIComponent(xsrf?.value ?? ''),
        Referer: page.url(),
      },
      data: { name: 'Not allowed' },
    })

    expect(answer.status()).toBe(403)
    expect((await answer.json()).code).toBe('forbidden')
  })
})

test.describe('Brands', () => {
  test('an Operations User adds a Brand with a logo, sees it, and deletes it', async ({
    page,
    isMobile,
  }) => {
    await signInAs(page, isMobile ? 'Admin' : 'Operations')
    await page.goto('/catalog/brands')
    const name = unique('Harbor Goods', isMobile)

    await page.getByRole('button', { name: 'New Brand' }).first().click()
    const dialog = page.getByRole('dialog', { name: 'New Brand' })
    await dialog.getByLabel('Name').fill(name)
    await dialog.getByLabel('Logo').setInputFiles({
      name: 'logo.png',
      mimeType: 'image/png',
      buffer: PNG,
    })
    await expect(dialog.getByRole('img', { name: `${name} logo` })).toBeVisible()
    await dialog.getByRole('button', { name: 'Create Brand' }).click()
    await expect(page.getByText('Brand created.')).toBeVisible()

    await page.getByRole('searchbox', { name: 'Search Brands' }).fill(name)
    const logo = page.getByRole('img', { name: `${name} logo` })
    await expect(logo).toBeVisible()
    // The logo is really served: the browser decoded the stored image.
    await expect
      .poll(() =>
        logo.evaluate((image) => (image as unknown as { naturalWidth: number }).naturalWidth),
      )
      .toBe(1)

    await openRowActions(page, name)
    await page.getByRole('menuitem', { name: 'Delete' }).click()
    await page.getByRole('alertdialog').getByRole('button', { name: 'Delete' }).click()
    await expect(page.getByText('Brand deleted.')).toBeVisible()
    await expect(page.getByText('No Brands match')).toBeVisible()
  })
})
