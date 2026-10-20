import { HttpResponse, http } from 'msw/http'
import type {
  Carrier,
  ShippingMethod,
  ShippingMethodInput,
  ShippingZone,
  ShippingZoneInput,
} from '@/features/shipping/types'
import { apiError } from './handlers'
import { listResponse } from './lists'
import { server } from './server'

let nextId = 500
const STAMP = '2026-10-01T09:00:00+00:00'

export function aShippingMethod(overrides: Partial<ShippingMethod> = {}): ShippingMethod {
  return {
    id: nextId++,
    shipping_zone_id: 1,
    name: 'Standard',
    price: '5.00',
    min_delivery_days: 2,
    max_delivery_days: 4,
    is_active: true,
    created_at: STAMP,
    updated_at: STAMP,
    ...overrides,
  }
}

export function aShippingZone(overrides: Partial<ShippingZone> = {}): ShippingZone {
  const id = overrides.id ?? nextId++
  return {
    id,
    name: `Zone ${id}`,
    locations: [],
    methods: [],
    created_at: STAMP,
    updated_at: STAMP,
    ...overrides,
  }
}

export function aCarrier(overrides: Partial<Carrier> = {}): Carrier {
  const id = overrides.id ?? nextId++
  return {
    id,
    name: `Carrier ${id}`,
    tracking_url_template: null,
    created_at: STAMP,
    updated_at: STAMP,
    ...overrides,
  }
}

const placeKey = (country: string, city: string | null) => `${country}/${city?.toLowerCase() ?? ''}`

/**
 * The Shipping zones API in memory, answering like the real one: the list convention
 * with method counts, a zone's page with its methods (cheapest first), 422 for a place
 * another zone covers, and the quote (city first, then country, else a message).
 */
export function shippingZoneStore(initial: ShippingZone[] = []) {
  const zones: ShippingZone[] = initial.map((zone) => ({ ...zone, methods: zone.methods ?? [] }))
  const bodies: unknown[] = []

  const withCounts = (zone: ShippingZone) => ({
    ...zone,
    methods: undefined,
    methods_count: zone.methods?.length ?? 0,
    active_methods_count: zone.methods?.filter((m) => m.is_active).length ?? 0,
  })
  const withMethods = (zone: ShippingZone) => ({
    ...zone,
    methods: [...(zone.methods ?? [])].sort((a, b) => Number(a.price) - Number(b.price)),
  })
  const find = (id: unknown) => zones.find((zone) => zone.id === Number(id))

  const saveZone = (input: ShippingZoneInput, existing?: ShippingZone): Response => {
    bodies.push(input)
    for (const [index, { country_code, city }] of input.locations.entries()) {
      const owner = zones.find(
        (zone) =>
          zone !== existing &&
          zone.locations.some(
            (l) => placeKey(l.country_code, l.city) === placeKey(country_code, city),
          ),
      )
      if (owner) {
        const message = `${city ?? country_code} is already in the Shipping zone "${owner.name}". A place can belong to one zone only.`
        return apiError(422, 'validation_failed', message, { [`locations.${index}`]: [message] })
      }
    }
    const zone = existing
      ? Object.assign(existing, input)
      : aShippingZone({ name: input.name, locations: input.locations, methods: [] })
    if (!existing) zones.push(zone)
    return HttpResponse.json({ data: withCounts(zone) }, { status: existing ? 200 : 201 })
  }

  server.use(
    http.get('*/api/v1/shipping-zones', ({ request }) =>
      listResponse(zones.map(withCounts), request, {
        searchIn: ['name'],
        defaultSort: 'name',
        filters: {
          country: (row, value) => row.locations.some((l) => l.country_code === value),
        },
      }),
    ),
    http.get('*/api/v1/shipping-zones/:id', ({ params }) => {
      const zone = find(params.id)
      return zone
        ? HttpResponse.json({ data: withMethods(zone) })
        : apiError(404, 'not_found', 'The requested resource was not found.')
    }),
    http.post('*/api/v1/shipping-zones', async ({ request }) =>
      saveZone((await request.json()) as ShippingZoneInput),
    ),
    http.put('*/api/v1/shipping-zones/:id', async ({ request, params }) =>
      saveZone((await request.json()) as ShippingZoneInput, find(params.id)),
    ),
    http.post('*/api/v1/shipping-zones/:id/methods', async ({ request, params }) => {
      const zone = find(params.id)!
      const input = (await request.json()) as ShippingMethodInput
      bodies.push(input)
      const method = aShippingMethod({
        ...input,
        price: Number(input.price).toFixed(2) as `${number}`,
        shipping_zone_id: zone.id,
      })
      zone.methods = [...(zone.methods ?? []), method]
      return HttpResponse.json({ data: method }, { status: 201 })
    }),
    http.get('*/api/v1/shipping/quote', ({ request }) => {
      const params = new URL(request.url).searchParams
      const country = params.get('country_code') ?? ''
      const city = params.get('city') ?? ''
      const covers = (key: string) =>
        zones.find((zone) => zone.locations.some((l) => placeKey(l.country_code, l.city) === key))
      const zone = covers(placeKey(country, city)) ?? covers(placeKey(country, null))
      if (!zone) {
        return HttpResponse.json({
          data: {
            zone: null,
            methods: [],
            message: `No Shipping zone covers ${city}, ${country}. Add the city or its country to a Shipping zone first.`,
          },
        })
      }
      const methods = withMethods(zone).methods.filter((method) => method.is_active)
      return HttpResponse.json({
        data: { zone: { id: zone.id, name: zone.name }, methods, message: null },
      })
    }),
  )

  return { zones, bodies }
}

/** The Carriers API in memory: the list convention and 422 for a taken name. */
export function carrierStore(initial: Carrier[] = []) {
  const carriers = [...initial]
  const bodies: unknown[] = []

  server.use(
    http.get('*/api/v1/carriers', ({ request }) =>
      listResponse(carriers, request, {
        searchIn: ['name'],
        defaultSort: 'name',
        filters: {
          tracking: (row, value) => (value === 'with') === (row.tracking_url_template !== null),
        },
      }),
    ),
    http.post('*/api/v1/carriers', async ({ request }) => {
      const input = (await request.json()) as { name: string; tracking_url_template: string }
      bodies.push(input)
      if (carriers.some((carrier) => carrier.name === input.name)) {
        return apiError(422, 'validation_failed', 'The name has already been taken.', {
          name: ['The name has already been taken.'],
        })
      }
      const carrier = aCarrier({
        name: input.name,
        tracking_url_template: input.tracking_url_template || null,
      })
      carriers.push(carrier)
      return HttpResponse.json({ data: carrier }, { status: 201 })
    }),
  )

  return { carriers, bodies }
}
