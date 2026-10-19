import { screen, waitFor, within } from '@testing-library/react'
import { describe, expect, it } from 'vitest'
import type { CurrentUser } from '@/features/auth/types'
import { aCategory, categoryStore, failingList } from '@/test/msw/catalog'
import { viewerUser } from '@/test/msw/handlers'
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

const clothing = aCategory({ id: 1, name: 'Clothing', children_count: 2 })
const footwear = aCategory({ id: 2, name: 'Footwear' })
const shirts = aCategory({
  id: 3,
  name: 'Shirts',
  parent: { id: 1, name: 'Clothing', slug: 'clothing' },
})
const trousers = aCategory({
  id: 4,
  name: 'Trousers',
  parent: { id: 1, name: 'Clothing', slug: 'clothing' },
})

function demoCategories() {
  return [clothing, footwear, shirts, trousers].map((category) => ({ ...category }))
}

async function openCategories(user: CurrentUser = operationsUser, path = '/catalog/categories') {
  signedInAs(user)
  const view = renderApp(path)
  // The first visit loads the page's code, which can take a moment on a busy machine.
  await screen.findByRole('heading', { name: 'Categories', level: 1 }, { timeout: 10_000 })
  return view
}

const table = () => screen.getByRole('table')
const rowNames = () =>
  within(table())
    .getAllByRole('row')
    .slice(1)
    .map((row) => within(row).getAllByRole('cell')[0]?.querySelector('[data-name]')?.textContent)

describe('the Categories list', () => {
  it('shows a skeleton while loading, then each Category with its parent and children', async () => {
    categoryStore(demoCategories())
    await openCategories()

    expect(screen.getByRole('status', { name: 'Loading Categories…' })).toBeInTheDocument()
    expect(await screen.findByText('Shirts')).toBeInTheDocument()

    const shirtsRow = screen.getByText('Shirts').closest('tr')!
    expect(within(shirtsRow).getByText('Clothing')).toBeInTheDocument()
    const clothingRow = screen.getAllByText('Clothing')[0]!.closest('tr')!
    expect(within(clothingRow).getByText('Top level')).toBeInTheDocument()
    expect(within(clothingRow).getByText('2')).toBeInTheDocument()
    expect(screen.getByText('Showing 1–4 of 4')).toBeInTheDocument()
  })

  it('offers to create the first Category when there are none', async () => {
    categoryStore([])
    const { user } = await openCategories()

    expect(await screen.findByText('No Categories yet')).toBeInTheDocument()
    await user.click(screen.getAllByRole('button', { name: 'New Category' }).at(-1)!)
    expect(await screen.findByRole('dialog', { name: 'New Category' })).toBeInTheDocument()
  })

  it('shows an error with a way to try again, and recovers', async () => {
    categoryStore(demoCategories())
    const failure = failingList('categories')
    const { user } = await openCategories()

    expect(await screen.findByText('Something went wrong')).toBeInTheDocument()
    failure.recover()
    await user.click(screen.getByRole('button', { name: 'Try again' }))

    expect(await screen.findByText('Shirts')).toBeInTheDocument()
  })

  it('says when nothing matches and clears the search and filters in one go', async () => {
    categoryStore(demoCategories())
    const { user, router } = await openCategories(operationsUser, '/catalog/categories?search=zzz')

    expect(await screen.findByText('No Categories match')).toBeInTheDocument()
    await user.click(screen.getAllByRole('button', { name: 'Clear filters' }).at(-1)!)

    expect(await screen.findByText('Shirts')).toBeInTheDocument()
    expect(router.state.location.search).toBe('')
  })
})

describe('the list state in the URL', () => {
  it('asks the API for exactly what a shared link describes', async () => {
    const store = categoryStore(demoCategories())
    await openCategories(
      operationsUser,
      '/catalog/categories?search=t&filter[level]=child&sort=-name&per_page=25',
    )

    await screen.findByText('Trousers')
    expect(store.listParams()).toContainEqual({
      search: 't',
      'filter[level]': 'child',
      sort: '-name',
      page: '1',
      per_page: '25',
    })
    expect(screen.getByRole('searchbox', { name: 'Search Categories' })).toHaveValue('t')
    expect(screen.getByRole('combobox', { name: 'Level' })).toHaveValue('child')
    expect(rowNames()).toEqual(['Trousers', 'Shirts'])
  })

  it('puts a filter in the URL and goes back to the first page', async () => {
    categoryStore(demoCategories())
    const { user, router } = await openCategories(operationsUser, '/catalog/categories?page=2')
    // Page 2 of four Categories is empty; filtering must still go back to page 1.
    await screen.findByRole('table')

    await user.selectOptions(screen.getByRole('combobox', { name: 'Level' }), 'top')

    await waitFor(() => expect(rowNames()).toEqual(['Clothing', 'Footwear']))
    expect(router.state.location.search).toBe('?filter%5Blevel%5D=top')
  })

  it('sorts by a column, then the other way round, with the order in the URL', async () => {
    categoryStore(demoCategories())
    const { user, router } = await openCategories()
    await screen.findByText('Shirts')

    await user.click(screen.getByRole('button', { name: 'Sort by Name' }))
    await waitFor(() => expect(rowNames()[0]).toBe('Trousers'))
    expect(router.state.location.search).toBe('?sort=-name')
    expect(screen.getByRole('columnheader', { name: /Name/ })).toHaveAttribute(
      'aria-sort',
      'descending',
    )

    await user.click(screen.getByRole('button', { name: 'Sort by Children' }))
    await waitFor(() => expect(router.state.location.search).toBe('?sort=children_count'))
  })

  it('searches as the User types, keeping one history entry for the search', async () => {
    const store = categoryStore(demoCategories())
    const { user, router } = await openCategories()
    await screen.findByText('Shirts')

    await user.type(screen.getByRole('searchbox', { name: 'Search Categories' }), 'shi')

    await waitFor(() => expect(rowNames()).toEqual(['Shirts']))
    expect(router.state.location.search).toBe('?search=shi')
    expect(router.state.historyAction).toBe('REPLACE')
    expect(store.listParams().at(-1)).toMatchObject({ search: 'shi', page: '1' })
  })

  it('pages through the list with links, and back returns to the previous page', async () => {
    const many = Array.from({ length: 20 }, (_, index) =>
      aCategory({ name: `Category ${String(index + 1).padStart(2, '0')}` }),
    )
    categoryStore(many)
    const { user, router } = await openCategories()
    await screen.findByText('Category 01')

    await user.click(screen.getByRole('link', { name: 'Next page' }))

    expect(await screen.findByText('Category 16')).toBeInTheDocument()
    expect(router.state.location.search).toBe('?page=2')
    expect(screen.getByText('Showing 16–20 of 20')).toBeInTheDocument()

    await router.navigate(-1)
    expect(await screen.findByText('Category 01')).toBeInTheDocument()
  })

  it('changes the page size', async () => {
    const store = categoryStore(demoCategories())
    const { user, router } = await openCategories()
    await screen.findByText('Shirts')

    await user.selectOptions(screen.getByRole('combobox', { name: 'Rows per page' }), '50')

    await waitFor(() => expect(router.state.location.search).toBe('?per_page=50'))
    await waitFor(() => expect(store.listParams().at(-1)).toMatchObject({ per_page: '50' }))
  })
})

describe('who may change Categories', () => {
  it('lists Categories in the sidebar for every Role that may see the catalog', async () => {
    categoryStore(demoCategories())
    await openCategories(viewerUser)

    const navigation = screen.getByRole('navigation', { name: 'Main navigation' })
    expect(within(navigation).getByRole('link', { name: 'Categories' })).toHaveAttribute(
      'href',
      '/catalog/categories',
    )
  })

  it('tells a User whose Role has no catalog access that the page is not for them', async () => {
    categoryStore(demoCategories())
    signedInAs({ ...viewerUser, permissions: ['dashboard.view'] })
    renderApp('/catalog/categories')

    expect(
      await screen.findByText("You can't open this page", {}, { timeout: 10_000 }),
    ).toBeInTheDocument()
    expect(screen.queryByRole('table')).not.toBeInTheDocument()
  })

  it('lets a Viewer look but offers no way to change anything', async () => {
    categoryStore(demoCategories())
    await openCategories(viewerUser)
    await screen.findByText('Shirts')

    expect(screen.queryByRole('button', { name: 'New Category' })).not.toBeInTheDocument()
    expect(screen.queryByRole('button', { name: /Actions for/ })).not.toBeInTheDocument()
  })

  it('tells a Viewer who adds Categories when there are none', async () => {
    categoryStore([])
    await openCategories(viewerUser)

    expect(await screen.findByText('No Categories yet')).toBeInTheDocument()
    expect(screen.queryByRole('button', { name: 'New Category' })).not.toBeInTheDocument()
  })
})

describe('creating and editing a Category', () => {
  it('checks the name before sending anything', async () => {
    const store = categoryStore([])
    const { user } = await openCategories()
    await screen.findByText('No Categories yet')

    await user.click(screen.getAllByRole('button', { name: 'New Category' })[0]!)
    const dialog = await screen.findByRole('dialog', { name: 'New Category' })
    await user.click(within(dialog).getByRole('button', { name: 'Create Category' }))

    expect(await within(dialog).findByText('Enter a name.')).toBeInTheDocument()
    expect(store.requests.filter(({ method }) => method === 'POST')).toHaveLength(0)
  })

  it('creates a child Category under a chosen top-level parent', async () => {
    const store = categoryStore(demoCategories())
    const { user } = await openCategories()
    await screen.findByText('Shirts')

    await user.click(screen.getByRole('button', { name: 'New Category' }))
    const dialog = await screen.findByRole('dialog', { name: 'New Category' })
    await user.type(within(dialog).getByLabelText('Name'), 'Jackets')
    const parent = within(dialog).getByRole('combobox', { name: 'Parent Category' })
    expect(
      within(parent)
        .getAllByRole('option')
        .map((option) => option.textContent),
    ).toEqual(['None (top level)', 'Clothing', 'Footwear'])
    await user.selectOptions(parent, 'Clothing')
    await user.click(within(dialog).getByRole('button', { name: 'Create Category' }))

    expect(await screen.findByText('Category created.')).toBeInTheDocument()
    expect(store.requests.find(({ method }) => method === 'POST')?.body).toEqual({
      name: 'Jackets',
      slug: '',
      parent_id: 1,
      description: '',
    })
    expect(await screen.findByText('Jackets')).toBeInTheDocument()
    expect(screen.queryByRole('dialog')).not.toBeInTheDocument()
  })

  it('shows the reason the API refuses a value under its field', async () => {
    categoryStore(demoCategories())
    const { user } = await openCategories()
    await screen.findByText('Shirts')

    await user.click(screen.getByRole('button', { name: 'New Category' }))
    const dialog = await screen.findByRole('dialog', { name: 'New Category' })
    await user.type(within(dialog).getByLabelText('Name'), 'Shirts again')
    await user.type(within(dialog).getByLabelText('Slug'), 'shirts')
    await user.click(within(dialog).getByRole('button', { name: 'Create Category' }))

    expect(await within(dialog).findByText('The slug has already been taken.')).toBeInTheDocument()
    expect(within(dialog).getByLabelText('Slug')).toHaveAttribute('aria-invalid', 'true')
  })

  it('refuses a slug in the wrong format before sending it', async () => {
    categoryStore([])
    const { user } = await openCategories()
    await screen.findByText('No Categories yet')

    await user.click(screen.getAllByRole('button', { name: 'New Category' })[0]!)
    const dialog = await screen.findByRole('dialog', { name: 'New Category' })
    await user.type(within(dialog).getByLabelText('Name'), 'Shirts')
    await user.type(within(dialog).getByLabelText('Slug'), 'Summer Shirts')
    await user.click(within(dialog).getByRole('button', { name: 'Create Category' }))

    expect(
      await within(dialog).findByText(
        'Use lowercase letters, digits and single dashes, for example "summer-shirts".',
      ),
    ).toBeInTheDocument()
  })

  it('edits a Category with its current values filled in, never offering itself as parent', async () => {
    const store = categoryStore(demoCategories())
    const { user } = await openCategories()
    await screen.findByText('Shirts')

    await user.click(screen.getByRole('button', { name: 'Actions for Footwear' }))
    await user.click(await screen.findByRole('menuitem', { name: 'Edit' }))
    const dialog = await screen.findByRole('dialog', { name: 'Edit Category' })
    expect(within(dialog).getByLabelText('Name')).toHaveValue('Footwear')
    expect(within(dialog).getByLabelText('Slug')).toHaveValue('footwear')
    const parent = within(dialog).getByRole('combobox', { name: 'Parent Category' })
    expect(within(parent).queryByRole('option', { name: 'Footwear' })).not.toBeInTheDocument()

    await user.clear(within(dialog).getByLabelText('Name'))
    await user.type(within(dialog).getByLabelText('Name'), 'Shoes')
    await user.click(within(dialog).getByRole('button', { name: 'Save changes' }))

    expect(await screen.findByText('Category saved.')).toBeInTheDocument()
    expect(store.requests.find(({ method }) => method === 'PUT')?.body).toMatchObject({
      name: 'Shoes',
      slug: 'footwear',
    })
    expect(await screen.findByText('Shoes')).toBeInTheDocument()
  })

  it('keeps a parent with children top-level, and says why', async () => {
    categoryStore(demoCategories())
    const { user } = await openCategories()
    await screen.findByText('Shirts')

    await user.click(screen.getByRole('button', { name: 'Actions for Clothing' }))
    await user.click(await screen.findByRole('menuitem', { name: 'Edit' }))
    const dialog = await screen.findByRole('dialog', { name: 'Edit Category' })

    expect(within(dialog).getByRole('combobox', { name: 'Parent Category' })).toBeDisabled()
    expect(
      within(dialog).getByText('Clothing has child Categories, so it stays top-level.'),
    ).toBeInTheDocument()
  })
})

describe('deleting a Category', () => {
  it('deletes after confirming', async () => {
    categoryStore(demoCategories())
    const { user } = await openCategories()
    await screen.findByText('Footwear')

    await user.click(screen.getByRole('button', { name: 'Actions for Footwear' }))
    await user.click(await screen.findByRole('menuitem', { name: 'Delete' }))
    const confirm = await screen.findByRole('alertdialog', { name: 'Delete Footwear?' })
    await user.click(within(confirm).getByRole('button', { name: 'Delete' }))

    expect(await screen.findByText('Category deleted.')).toBeInTheDocument()
    await waitFor(() => expect(screen.queryByText('Footwear')).not.toBeInTheDocument())
  })

  it('explains why the API refuses to delete a parent that still has children', async () => {
    categoryStore(demoCategories())
    const { user } = await openCategories()
    await screen.findByText('Shirts')

    await user.click(screen.getByRole('button', { name: 'Actions for Clothing' }))
    await user.click(await screen.findByRole('menuitem', { name: 'Delete' }))
    const confirm = await screen.findByRole('alertdialog', { name: 'Delete Clothing?' })
    await user.click(within(confirm).getByRole('button', { name: 'Delete' }))

    expect(
      await screen.findByText('Clothing still has child Categories. Move or delete them first.'),
    ).toBeInTheDocument()
    expect(screen.getAllByText('Clothing').length).toBeGreaterThan(0)
  })
})
