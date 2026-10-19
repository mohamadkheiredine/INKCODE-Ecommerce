import { delay } from 'msw'
import { HttpResponse, http } from 'msw/http'
import type { Brand, Category } from '@/features/catalog/types'
import { apiError } from './handlers'
import { listResponse } from './lists'
import { server } from './server'

const slugify = (name: string) =>
  name
    .toLowerCase()
    .replace(/[^a-z0-9]+/g, '-')
    .replace(/^-|-$/g, '') || 'category'

let nextId = 100

export function aCategory(overrides: Partial<Category> = {}): Category {
  const id = overrides.id ?? nextId++
  const name = overrides.name ?? `Category ${id}`
  return {
    id,
    name,
    slug: slugify(name),
    description: null,
    parent: null,
    children_count: 0,
    created_at: '2026-10-01T09:00:00+00:00',
    updated_at: '2026-10-01T09:00:00+00:00',
    ...overrides,
  }
}

export function aBrand(overrides: Partial<Brand> = {}): Brand {
  const id = overrides.id ?? nextId++
  const name = overrides.name ?? `Brand ${id}`
  return {
    id,
    name,
    slug: slugify(name),
    logo_url: null,
    created_at: '2026-10-01T09:00:00+00:00',
    updated_at: '2026-10-01T09:00:00+00:00',
    ...overrides,
  }
}

type Recorded = { method: string; url: URL; body: unknown }

function recorder() {
  const requests: Recorded[] = []
  const record = async (request: Request) => {
    const type = request.headers.get('Content-Type') ?? ''
    const body = type.includes('application/json') ? await request.clone().json() : null
    requests.push({ method: request.method, url: new URL(request.url), body })
  }
  /** The query parameters of every list request, in order. */
  const listParams = (path: string) =>
    requests
      .filter(({ method, url }) => method === 'GET' && url.pathname.endsWith(path))
      .map(({ url }) => Object.fromEntries(url.searchParams))
  return { requests, record, listParams }
}

/**
 * The Categories API, kept in memory and answering like the real one: the list
 * convention, a generated slug, two levels at most, 422 for a taken slug and 409 for
 * deleting a parent that still has children.
 */
export function categoryStore(initial: Category[] = []) {
  const categories = [...initial]
  const { requests, record, listParams } = recorder()

  const countChildren = () =>
    categories.forEach((category) => {
      category.children_count = categories.filter((c) => c.parent?.id === category.id).length
    })

  type Body = { name?: string; slug?: string; parent_id?: number | null; description?: string }

  const save = (body: Body, existing?: Category): Response => {
    if (!body.name?.trim()) {
      return apiError(422, 'validation_failed', 'The name field is required.', {
        name: ['The name field is required.'],
      })
    }
    const slug = body.slug || slugify(body.name)
    if (categories.some((c) => c.slug === slug && c.id !== existing?.id)) {
      return apiError(422, 'validation_failed', 'The slug has already been taken.', {
        slug: ['The slug has already been taken.'],
      })
    }
    const parent = categories.find((c) => c.id === body.parent_id) ?? null
    const values = {
      name: body.name.trim(),
      slug,
      description: body.description || null,
      parent: parent && { id: parent.id, name: parent.name, slug: parent.slug },
    }
    const category = existing ? Object.assign(existing, values) : aCategory(values)
    if (!existing) categories.push(category)
    countChildren()
    return HttpResponse.json({ data: category }, { status: existing ? 200 : 201 })
  }

  server.use(
    http.get('*/api/v1/categories', async ({ request }) => {
      await record(request)
      return listResponse(categories, request, {
        searchIn: ['name', 'slug'],
        defaultSort: 'name',
        filters: {
          level: (row, value) => (value === 'top' ? row.parent === null : row.parent !== null),
          parent_id: (row, value) => row.parent?.id === Number(value),
        },
      })
    }),
    http.post('*/api/v1/categories', async ({ request }) => {
      await record(request)
      return save((await request.json()) as Body)
    }),
    http.put('*/api/v1/categories/:id', async ({ request, params }) => {
      await record(request)
      const existing = categories.find((c) => c.id === Number(params.id))
      if (!existing) return apiError(404, 'not_found', 'The requested resource was not found.')
      return save((await request.json()) as Body, existing)
    }),
    http.delete('*/api/v1/categories/:id', async ({ request, params }) => {
      await record(request)
      const index = categories.findIndex((c) => c.id === Number(params.id))
      const category = categories[index]
      if (!category) return apiError(404, 'not_found', 'The requested resource was not found.')
      if (category.children_count > 0) {
        return apiError(
          409,
          'category_has_children',
          `${category.name} still has child Categories. Move or delete them first.`,
        )
      }
      categories.splice(index, 1)
      countChildren()
      return new HttpResponse(null, { status: 204 })
    }),
  )

  return { categories, requests, listParams: () => listParams('/categories') }
}

/**
 * The Brands API, kept in memory: the list convention, a generated slug, 422 for a
 * taken slug, and the logo endpoints (any upload becomes a fixed URL).
 */
export function brandStore(initial: Brand[] = []) {
  const brands = [...initial]
  const { requests, record, listParams } = recorder()
  /** The file name of every logo uploaded, in order. */
  const uploads: string[] = []

  type Body = { name?: string; slug?: string }

  const save = (body: Body, existing?: Brand): Response => {
    if (!body.name?.trim()) {
      return apiError(422, 'validation_failed', 'The name field is required.', {
        name: ['The name field is required.'],
      })
    }
    const slug = body.slug || slugify(body.name)
    if (brands.some((b) => b.slug === slug && b.id !== existing?.id)) {
      return apiError(422, 'validation_failed', 'The slug has already been taken.', {
        slug: ['The slug has already been taken.'],
      })
    }
    const brand = existing
      ? Object.assign(existing, { name: body.name.trim(), slug })
      : aBrand({ name: body.name.trim(), slug })
    if (!existing) brands.push(brand)
    return HttpResponse.json({ data: brand }, { status: existing ? 200 : 201 })
  }

  const find = (id: unknown) => brands.find((b) => b.id === Number(id))

  server.use(
    http.get('*/api/v1/brands', async ({ request }) => {
      await record(request)
      return listResponse(brands, request, {
        searchIn: ['name', 'slug'],
        defaultSort: 'name',
        filters: { logo: (row, value) => (value === 'with') === (row.logo_url !== null) },
      })
    }),
    http.post('*/api/v1/brands', async ({ request }) => {
      await record(request)
      return save((await request.json()) as Body)
    }),
    http.put('*/api/v1/brands/:id', async ({ request, params }) => {
      await record(request)
      const brand = find(params.id)
      return brand ? save((await request.json()) as Body, brand) : apiError(404, 'not_found', '')
    }),
    http.delete('*/api/v1/brands/:id', async ({ request, params }) => {
      await record(request)
      const index = brands.findIndex((b) => b.id === Number(params.id))
      if (index < 0) return apiError(404, 'not_found', '')
      brands.splice(index, 1)
      return new HttpResponse(null, { status: 204 })
    }),
    http.post('*/api/v1/brands/:id/logo', async ({ request, params }) => {
      await record(request)
      const brand = find(params.id)
      if (!brand) return apiError(404, 'not_found', '')
      // Read as text: Node's form-data parser refuses jsdom's File objects.
      const filename = /name="logo"; filename="([^"]+)"/.exec(await request.text())?.[1]
      if (!filename) {
        return apiError(422, 'validation_failed', 'The logo field is required.', {
          logo: ['The logo field is required.'],
        })
      }
      uploads.push(filename)
      brand.logo_url = `http://localhost:8000/storage/brands/${brand.id}.png`
      return HttpResponse.json({ data: brand })
    }),
    http.delete('*/api/v1/brands/:id/logo', async ({ request, params }) => {
      await record(request)
      const brand = find(params.id)
      if (!brand) return apiError(404, 'not_found', '')
      brand.logo_url = null
      return HttpResponse.json({ data: brand })
    }),
  )

  return { brands, requests, uploads, listParams: () => listParams('/brands') }
}

/** Every list request for this path fails until `recover()` is called. */
export function failingList(path: string) {
  let failing = true
  server.use(
    http.get(`*/api/v1/${path}`, async () => {
      await delay(10)
      if (failing) return apiError(500, 'server_error', 'Something went wrong on our side.')
      return undefined
    }),
  )
  return { recover: () => (failing = false) }
}
