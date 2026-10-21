import { HttpResponse, http } from 'msw/http'
import type { Customer, CustomerNote, CustomerProfile, Tag } from '@/features/customers/types'
import { apiError } from './handlers'
import { listResponse } from './lists'
import { server } from './server'

let nextId = 500

export function aCustomer(overrides: Partial<CustomerProfile> = {}): CustomerProfile {
  const id = overrides.id ?? nextId++
  return {
    id,
    name: `Customer ${id}`,
    email: `customer${id}@example.com`,
    phone: null,
    lifetime_value: '0.00',
    last_order_at: null,
    segment: 'regular',
    tags: [],
    addresses: [],
    created_at: '2026-10-01T09:00:00+00:00',
    updated_at: '2026-10-01T09:00:00+00:00',
    ...overrides,
  }
}

type Body = { name?: string; email?: string | null; phone?: string | null; tags?: string[] }

/**
 * The Customers API, kept in memory: the list convention (search, Tag filter), Tags by
 * name, 422 without an email or a phone, the profile and its notes.
 */
export function customerStore(initial: CustomerProfile[] = [], tags: Tag[] = []) {
  const customers = [...initial]
  const notes: Record<number, CustomerNote[]> = {}
  const bodies: Body[] = []

  const toTags = (names: string[] = []) =>
    names.map((name) => {
      const tag = tags.find((t) => t.name.toLowerCase() === name.toLowerCase())
      if (tag) return tag
      const created = { id: nextId++, name }
      tags.push(created)
      return created
    })

  const save = (body: Body, existing?: CustomerProfile): Response => {
    bodies.push(body)
    if (!body.email && !body.phone) {
      const message = 'Enter an email or a phone number so the Customer can be reached.'
      return apiError(422, 'validation_failed', message, { email: [message], phone: [message] })
    }
    const values = {
      name: body.name ?? '',
      email: body.email ?? null,
      phone: body.phone ?? null,
      tags: toTags(body.tags),
    }
    const customer = existing ? Object.assign(existing, values) : aCustomer(values)
    if (!existing) customers.push(customer)
    return HttpResponse.json({ data: customer }, { status: existing ? 200 : 201 })
  }

  const find = (id: unknown) => customers.find((c) => c.id === Number(id))

  server.use(
    http.get('*/api/v1/customers', ({ request }) =>
      listResponse<Customer>(customers, request, {
        searchIn: ['name', 'email', 'phone'],
        defaultSort: '-created_at',
        filters: {
          tag: (row, value) => row.tags.some((tag) => tag.id === Number(value)),
          segment: (row, value) => row.segment === value,
        },
      }),
    ),
    http.get('*/api/v1/customer-tags', () =>
      HttpResponse.json({ data: tags, meta: { total: tags.length } }),
    ),
    http.post('*/api/v1/customers', async ({ request }) => save((await request.json()) as Body)),
    http.get('*/api/v1/customers/:id', ({ params }) => {
      const customer = find(params.id)
      return customer
        ? HttpResponse.json({ data: customer })
        : apiError(404, 'not_found', 'The requested resource was not found.')
    }),
    http.get('*/api/v1/customers/:id/notes', ({ params }) => {
      const data = notes[Number(params.id)] ?? []
      return HttpResponse.json({
        data,
        meta: { current_page: 1, last_page: 1, per_page: 15, total: data.length },
      })
    }),
    http.post('*/api/v1/customers/:id/notes', async ({ request, params }) => {
      const { body } = (await request.json()) as { body: string }
      const note = {
        id: nextId++,
        body,
        author: { id: 1, name: 'Maya Support' },
        created_at: new Date().toISOString(),
      }
      ;(notes[Number(params.id)] ??= []).unshift(note)
      return HttpResponse.json({ data: note }, { status: 201 })
    }),
  )

  return { customers, tags, bodies }
}
