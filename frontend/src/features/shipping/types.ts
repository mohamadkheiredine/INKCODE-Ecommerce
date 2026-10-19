import type { Amount } from '@/lib/format'

/** A place a Shipping zone covers: a whole country (`city` null) or one city in it. */
export type ZoneLocation = { country_code: string; city: string | null }

/** A Shipping method as the API sends it. `price` is a decimal string ("7.50"). */
export type ShippingMethod = {
  id: number
  shipping_zone_id: number
  name: string
  price: Amount
  min_delivery_days: number
  max_delivery_days: number
  is_active: boolean
  created_at: string
  updated_at: string
}

/** A Shipping zone; the list sends method counts, the zone's own page its methods. */
export type ShippingZone = {
  id: number
  name: string
  locations: ZoneLocation[]
  methods_count?: number
  active_methods_count?: number
  methods?: ShippingMethod[]
  created_at: string
  updated_at: string
}

export type ShippingZoneInput = { name: string; locations: ZoneLocation[] }

export type ShippingMethodInput = {
  name: string
  price: string
  min_delivery_days: number
  max_delivery_days: number
  is_active: boolean
}

export type Carrier = {
  id: number
  name: string
  tracking_url_template: string | null
  created_at: string
  updated_at: string
}

export type CarrierInput = { name: string; tracking_url_template: string }

/** What can be shipped to an address. When nothing can, `message` says why. */
export type ShippingQuote = {
  zone: { id: number; name: string } | null
  methods: Pick<
    ShippingMethod,
    'id' | 'name' | 'price' | 'min_delivery_days' | 'max_delivery_days'
  >[]
  message: string | null
}

export type QuoteAddress = { country_code: string; city: string }
