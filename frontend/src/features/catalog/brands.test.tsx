import { screen, waitFor, within } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { HttpResponse, http } from 'msw/http'
import { describe, expect, it } from 'vitest'
import type { CurrentUser } from '@/features/auth/types'
import { aBrand, brandStore } from '@/test/msw/catalog'
import { apiError, viewerUser } from '@/test/msw/handlers'
import { server } from '@/test/msw/server'
import { signedInAs } from '@/test/msw/session'
import { renderApp } from '@/test/render-app'

const operationsUser: CurrentUser = {
  id: 2,
  name: 'Rania Khoury',
  email: 'operations@inkcode.test',
  role: 'operations',
  permissions: ['dashboard.view', 'catalog.view', 'catalog.manage'],
  preferences: { locale: 'en', theme: 'light' },
}

function demoBrands() {
  return [
    aBrand({
      id: 1,
      name: 'Nordic Wear',
      logo_url: 'http://localhost:8000/storage/brands/nordic.png',
    }),
    aBrand({ id: 2, name: 'Atlas Outdoor' }),
    aBrand({ id: 3, name: 'Zest' }),
  ]
}

async function openBrands(user: CurrentUser = operationsUser, path = '/catalog/brands') {
  signedInAs(user)
  const view = renderApp(path)
  // The first visit loads the page's code, which can take a moment on a busy machine.
  await screen.findByRole('heading', { name: 'Brands', level: 1 }, { timeout: 10_000 })
  return view
}

const png = (name = 'logo.png', bytes = 2048) =>
  new File([new Uint8Array(bytes)], name, { type: 'image/png' })

async function openNewBrand(user: ReturnType<typeof renderApp>['user']) {
  await user.click(screen.getAllByRole('button', { name: 'New Brand' })[0]!)
  return screen.findByRole('dialog', { name: 'New Brand' })
}

describe('the Brands list', () => {
  it('shows each Brand with its logo, or its initials when it has none', async () => {
    brandStore(demoBrands())
    await openBrands()

    expect(await screen.findByRole('img', { name: 'Nordic Wear logo' })).toHaveAttribute(
      'src',
      'http://localhost:8000/storage/brands/nordic.png',
    )
    const atlasRow = screen.getByText('Atlas Outdoor').closest('tr')!
    expect(within(atlasRow).getByText('AO')).toBeInTheDocument()
  })

  it('filters by logo, keeping the filter in the URL', async () => {
    const store = brandStore(demoBrands())
    const { user, router } = await openBrands()
    await screen.findByText('Zest')

    await user.selectOptions(screen.getByRole('combobox', { name: 'Logo' }), 'without')

    await waitFor(() => expect(screen.queryByText('Nordic Wear')).not.toBeInTheDocument())
    expect(router.state.location.search).toBe('?filter%5Blogo%5D=without')
    expect(store.listParams().at(-1)).toMatchObject({ 'filter[logo]': 'without' })
  })

  it('offers to add the first Brand when there are none', async () => {
    brandStore([])
    await openBrands()

    expect(await screen.findByText('No Brands yet')).toBeInTheDocument()
    expect(screen.getAllByRole('button', { name: 'New Brand' })).toHaveLength(2)
  })

  it('lets a Viewer look but offers no way to change anything', async () => {
    brandStore(demoBrands())
    await openBrands(viewerUser)
    await screen.findByText('Zest')

    expect(screen.queryByRole('button', { name: 'New Brand' })).not.toBeInTheDocument()
    expect(screen.queryByRole('button', { name: /Actions for/ })).not.toBeInTheDocument()
  })
})

describe('creating and editing a Brand', () => {
  it('creates a Brand, then uploads its logo', async () => {
    const store = brandStore([])
    const { user } = await openBrands()
    await screen.findByText('No Brands yet')

    const dialog = await openNewBrand(user)
    await user.type(within(dialog).getByLabelText('Name'), 'Cedar & Co.')
    await user.upload(within(dialog).getByLabelText('Logo'), png())
    expect(within(dialog).getByRole('img', { name: 'Cedar & Co. logo' })).toBeInTheDocument()
    await user.click(within(dialog).getByRole('button', { name: 'Create Brand' }))

    expect(await screen.findByText('Brand created.')).toBeInTheDocument()
    expect(store.requests.map(({ method, url }) => `${method} ${url.pathname}`)).toEqual(
      expect.arrayContaining([
        'POST /api/v1/brands',
        `POST /api/v1/brands/${store.brands[0]!.id}/logo`,
      ]),
    )
    expect(store.uploads).toEqual(['logo.png'])
    expect(await screen.findByRole('img', { name: 'Cedar & Co. logo' })).toBeInTheDocument()
  })

  it('refuses a logo that is not a PNG, JPEG or WebP image, before sending anything', async () => {
    const store = brandStore([])
    signedInAs(operationsUser)
    renderApp('/catalog/brands')
    await screen.findByText('No Brands yet', {}, { timeout: 10_000 })
    const user = userEvent.setup({ applyAccept: false })

    const dialog = await openNewBrand(user)
    await user.type(within(dialog).getByLabelText('Name'), 'Zest')
    await user.upload(
      within(dialog).getByLabelText('Logo'),
      new File(['<svg/>'], 'logo.svg', { type: 'image/svg+xml' }),
    )
    await user.click(within(dialog).getByRole('button', { name: 'Create Brand' }))

    expect(await within(dialog).findByText('Choose a PNG, JPEG or WebP image.')).toBeInTheDocument()
    expect(store.requests.filter(({ method }) => method === 'POST')).toHaveLength(0)
  })

  it('refuses a logo over 1 MB', async () => {
    brandStore([])
    const { user } = await openBrands()
    await screen.findByText('No Brands yet')

    const dialog = await openNewBrand(user)
    await user.type(within(dialog).getByLabelText('Name'), 'Zest')
    await user.upload(within(dialog).getByLabelText('Logo'), png('big.png', 1024 * 1024 + 1))
    await user.click(within(dialog).getByRole('button', { name: 'Create Brand' }))

    expect(await within(dialog).findByText('Choose an image of 1 MB or less.')).toBeInTheDocument()
  })

  it('shows the reason the API refuses a slug under the slug field', async () => {
    brandStore(demoBrands())
    const { user } = await openBrands()
    await screen.findByText('Zest')

    const dialog = await openNewBrand(user)
    await user.type(within(dialog).getByLabelText('Name'), 'Zest again')
    await user.type(within(dialog).getByLabelText('Slug'), 'zest')
    await user.click(within(dialog).getByRole('button', { name: 'Create Brand' }))

    expect(await within(dialog).findByText('The slug has already been taken.')).toBeInTheDocument()
    expect(within(dialog).getByLabelText('Slug')).toHaveAttribute('aria-invalid', 'true')
  })

  it('saves the Brand even when its logo is refused, and says so', async () => {
    brandStore([])
    server.use(
      http.post('*/api/v1/brands/:id/logo', () =>
        apiError(422, 'validation_failed', 'The logo field must be an image.', {
          logo: ['The logo field must be an image.'],
        }),
      ),
    )
    const { user } = await openBrands()
    await screen.findByText('No Brands yet')

    const dialog = await openNewBrand(user)
    await user.type(within(dialog).getByLabelText('Name'), 'Zest')
    await user.upload(within(dialog).getByLabelText('Logo'), png())
    await user.click(within(dialog).getByRole('button', { name: 'Create Brand' }))

    expect(
      await screen.findByText(
        "The Brand was saved, but its logo wasn't: The logo field must be an image.",
      ),
    ).toBeInTheDocument()
    expect(await screen.findByText('Zest')).toBeInTheDocument()
  })

  it("removes a Brand's logo when editing", async () => {
    const store = brandStore(demoBrands())
    const { user } = await openBrands()
    await screen.findByText('Zest')

    await user.click(screen.getByRole('button', { name: 'Actions for Nordic Wear' }))
    await user.click(await screen.findByRole('menuitem', { name: 'Edit' }))
    const dialog = await screen.findByRole('dialog', { name: 'Edit Brand' })
    expect(within(dialog).getByLabelText('Name')).toHaveValue('Nordic Wear')
    await user.click(within(dialog).getByRole('button', { name: 'Remove logo' }))
    await user.click(within(dialog).getByRole('button', { name: 'Save changes' }))

    expect(await screen.findByText('Brand saved.')).toBeInTheDocument()
    expect(store.requests.map(({ method, url }) => `${method} ${url.pathname}`)).toContain(
      'DELETE /api/v1/brands/1/logo',
    )
    await waitFor(() =>
      expect(screen.queryByRole('img', { name: 'Nordic Wear logo' })).not.toBeInTheDocument(),
    )
  })

  it('changes nothing about the logo when only the name changes', async () => {
    const store = brandStore(demoBrands())
    const { user } = await openBrands()
    await screen.findByText('Zest')

    await user.click(screen.getByRole('button', { name: 'Actions for Nordic Wear' }))
    await user.click(await screen.findByRole('menuitem', { name: 'Edit' }))
    const dialog = await screen.findByRole('dialog', { name: 'Edit Brand' })
    await user.type(within(dialog).getByLabelText('Name'), ' Studio')
    await user.click(within(dialog).getByRole('button', { name: 'Save changes' }))

    expect(await screen.findByText('Brand saved.')).toBeInTheDocument()
    expect(store.requests.filter(({ url }) => url.pathname.endsWith('/logo'))).toHaveLength(0)
    expect(store.requests.find(({ method }) => method === 'PUT')?.body).toEqual({
      name: 'Nordic Wear Studio',
      slug: 'nordic-wear',
    })
  })
})

describe('deleting a Brand', () => {
  it('deletes after confirming', async () => {
    brandStore(demoBrands())
    const { user } = await openBrands()
    await screen.findByText('Zest')

    await user.click(screen.getByRole('button', { name: 'Actions for Zest' }))
    await user.click(await screen.findByRole('menuitem', { name: 'Delete' }))
    const confirm = await screen.findByRole('alertdialog', { name: 'Delete Zest?' })
    await user.click(within(confirm).getByRole('button', { name: 'Delete' }))

    expect(await screen.findByText('Brand deleted.')).toBeInTheDocument()
    await waitFor(() => expect(screen.queryByText('Zest')).not.toBeInTheDocument())
  })

  it('explains why the API refuses to delete a Brand in use', async () => {
    brandStore(demoBrands())
    server.use(
      http.delete('*/api/v1/brands/:id', () =>
        HttpResponse.json(
          {
            message: 'Zest is still used by Products. Move those Products to another Brand first.',
            code: 'in_use',
          },
          { status: 409 },
        ),
      ),
    )
    const { user } = await openBrands()
    await screen.findByText('Zest')

    await user.click(screen.getByRole('button', { name: 'Actions for Zest' }))
    await user.click(await screen.findByRole('menuitem', { name: 'Delete' }))
    await user.click(
      within(await screen.findByRole('alertdialog')).getByRole('button', { name: 'Delete' }),
    )

    expect(
      await screen.findByText(
        'Zest is still used by Products. Move those Products to another Brand first.',
      ),
    ).toBeInTheDocument()
  })
})
