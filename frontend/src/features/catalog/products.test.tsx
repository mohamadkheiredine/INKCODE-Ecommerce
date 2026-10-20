import { screen, waitFor, within } from '@testing-library/react'
import { HttpResponse, http } from 'msw/http'
import { describe, expect, it } from 'vitest'
import type { CurrentUser } from '@/features/auth/types'
import type { Attribute, Product, ProductListItem, Variant } from '@/features/catalog/types'
import { apiError } from '@/test/msw/handlers'
import { listResponse } from '@/test/msw/lists'
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

const fields = {
  slug: 'classic-tee',
  description: null,
  category: { id: 1, name: 'Shirts', slug: 'shirts' },
  brand: null,
  base_price: '19.90',
  taxable: true,
  tags: [],
  seo_title: null,
  seo_description: null,
  status: 'active',
  created_at: '2026-10-01T09:00:00+00:00',
  updated_at: '2026-10-01T09:00:00+00:00',
} as const

const tee: ProductListItem = {
  ...fields,
  id: 5,
  name: 'Classic Tee',
  main_image_url: null,
  price_min: '19.90',
  price_max: '24.00',
  variants_count: 12,
}

const library: Attribute[] = [
  {
    id: 1,
    name: 'Color',
    values: ['Red', 'Blue', 'Black'].map((name, i) => ({
      id: 11 + i,
      name,
      code: name.toUpperCase(),
    })),
  },
  {
    id: 2,
    name: 'Size',
    values: ['S', 'M', 'L', 'XL'].map((name, i) => ({ id: 21 + i, name, code: name })),
  },
].map((attribute) => ({
  ...attribute,
  created_at: fields.created_at,
  updated_at: fields.updated_at,
}))

const redS: Variant = {
  id: 100,
  sku: 'CLASSIC-TEE-RED-S',
  price: '19.90',
  average_cost: '0.0000',
  low_stock_threshold: null,
  options: [
    { attribute: 'Color', value: 'Red' },
    { attribute: 'Size', value: 'S' },
  ],
  value_ids: [11, 21],
}

function aProduct(variants: Variant[]): Product {
  return {
    ...fields,
    id: 5,
    name: 'Classic Tee',
    images: [],
    attributes: variants.length
      ? [
          { id: 1, name: 'Color', value_ids: [11] },
          { id: 2, name: 'Size', value_ids: [21] },
        ]
      : [],
    variants,
  }
}

/** The pickers' lists, which every Product screen loads. */
function catalogLists() {
  server.use(
    http.get('*/api/v1/categories', ({ request }) =>
      listResponse([], request, { searchIn: [], defaultSort: 'name' }),
    ),
    http.get('*/api/v1/brands', ({ request }) =>
      listResponse([], request, { searchIn: [], defaultSort: 'name' }),
    ),
    http.get('*/api/v1/attributes', ({ request }) =>
      listResponse(library, request, { searchIn: ['name'], defaultSort: 'name' }),
    ),
  )
}

async function open(path: string, heading: string) {
  signedInAs(operationsUser)
  catalogLists()
  const view = renderApp(path)
  await screen.findByRole('heading', { name: heading, level: 1 }, { timeout: 10_000 })
  return view
}

describe('the Products list', () => {
  it('shows each Product with its price range and filters on the server', async () => {
    const asked: URLSearchParams[] = []
    server.use(
      http.get('*/api/v1/products', ({ request }) => {
        asked.push(new URL(request.url).searchParams)
        return listResponse([tee], request, { searchIn: ['name'], defaultSort: 'name' })
      }),
    )
    const { user } = await open('/catalog/products', 'Products')

    const row = (await screen.findByRole('link', { name: 'Classic Tee' })).closest('tr')!
    expect(within(row).getByText('$19.90 – $24.00')).toBeInTheDocument()
    expect(within(row).getByText('12 Variants')).toBeInTheDocument()

    await user.selectOptions(screen.getByRole('combobox', { name: 'Status' }), 'archived')
    await user.type(screen.getByRole('textbox', { name: 'Lowest price' }), '20{Enter}')
    await waitFor(() => expect(asked.at(-1)?.get('filter[price_min]')).toBe('20'))
    expect(asked.at(-1)?.get('filter[status]')).toBe('archived')
  })

  it('archives the selected Products in bulk', async () => {
    let body: unknown
    server.use(
      http.get('*/api/v1/products', ({ request }) =>
        listResponse([tee], request, { searchIn: ['name'], defaultSort: 'name' }),
      ),
      http.post('*/api/v1/products/bulk', async ({ request }) => {
        body = await request.json()
        return HttpResponse.json({ data: { changed: 1, deleted: 0, skipped: [] } })
      }),
    )
    const { user } = await open('/catalog/products', 'Products')

    await user.click(await screen.findByRole('checkbox', { name: 'Select Classic Tee' }))
    await user.click(screen.getByRole('button', { name: 'Archive' }))

    expect(await screen.findByText('1 Product updated.')).toBeInTheDocument()
    expect(body).toEqual({ action: 'archive', product_ids: [5] })
  })
})

describe('the Product editor', () => {
  it('generates every missing combination of the chosen values', async () => {
    let asked: unknown
    server.use(
      http.get('*/api/v1/products/5', () => HttpResponse.json({ data: aProduct([]) })),
      http.post('*/api/v1/products/5/variants/generate', async ({ request }) => {
        asked = await request.json()
        return HttpResponse.json({ data: aProduct([redS]), meta: { generated: 12 } })
      }),
    )
    const { user } = await open('/catalog/products/5', 'Classic Tee')

    for (const value of ['Red', 'Blue', 'Black', 'S', 'M', 'L', 'XL']) {
      await user.click(await screen.findByRole('button', { name: value }))
    }
    await user.click(screen.getByRole('button', { name: 'Generate 12 Variants' }))

    expect(await screen.findByText('12 Variants generated.')).toBeInTheDocument()
    expect(asked).toEqual({ attribute_value_ids: [11, 12, 13, 21, 22, 23, 24] })
    expect(
      await screen.findByRole('textbox', { name: 'SKU CLASSIC-TEE-RED-S' }),
    ).toBeInTheDocument()
  })

  it("shows the API's refusal of a taken SKU under that Variant's SKU", async () => {
    server.use(
      http.get('*/api/v1/products/5', () => HttpResponse.json({ data: aProduct([redS]) })),
      http.put('*/api/v1/products/5/variants', () =>
        apiError(422, 'validation_failed', 'The given data was invalid.', {
          'variants.0.sku': ['Another Variant already uses the SKU TAKEN-1.'],
        }),
      ),
    )
    const { user } = await open('/catalog/products/5', 'Classic Tee')

    const sku = await screen.findByRole('textbox', { name: 'SKU CLASSIC-TEE-RED-S' })
    await user.clear(sku)
    await user.type(sku, 'TAKEN-1')
    await user.click(screen.getByRole('button', { name: 'Save Variants' }))

    expect(
      await screen.findByText('Another Variant already uses the SKU TAKEN-1.'),
    ).toBeInTheDocument()
    expect(sku).toHaveAttribute('aria-invalid', 'true')
  })
})
