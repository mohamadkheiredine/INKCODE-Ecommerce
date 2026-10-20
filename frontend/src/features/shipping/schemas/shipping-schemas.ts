import { z } from 'zod'
import type {
  Carrier,
  ShippingMethod,
  ShippingMethodInput,
  ShippingZone,
  ShippingZoneInput,
} from '../types'

const name = z
  .string()
  .trim()
  .min(1, 'shipping:validation.nameRequired')
  .max(120, 'shipping:validation.nameTooLong')

/* Shipping zone ------------------------------------------------------------------ */

export const zoneSchema = z.object({
  name,
  locations: z
    .array(
      z.object({
        country_code: z.string().min(1, 'shipping:validation.countryRequired'),
        city: z.string().trim().max(120, 'shipping:validation.cityTooLong'),
      }),
    )
    .min(1, 'shipping:validation.locationsRequired'),
})

export type ZoneFormValues = z.infer<typeof zoneSchema>

export const EMPTY_LOCATION = { country_code: '', city: '' }

export function zoneFormValues(zone: ShippingZone | null): ZoneFormValues {
  return {
    name: zone?.name ?? '',
    locations: zone?.locations.map((location) => ({
      country_code: location.country_code,
      city: location.city ?? '',
    })) ?? [EMPTY_LOCATION],
  }
}

/** An empty city means the whole country. */
export function toZoneInput(values: ZoneFormValues): ShippingZoneInput {
  return {
    name: values.name,
    locations: values.locations.map(({ country_code, city }) => ({
      country_code,
      city: city === '' ? null : city,
    })),
  }
}

/* Shipping method ---------------------------------------------------------------- */

/** What the API accepts as a price (SaveShippingMethodRequest): up to 99,999,999.99, two decimals. */
const PRICE_PATTERN = /^\d{1,8}(\.\d{1,2})?$/
const MAX_DELIVERY_DAYS = 365

const deliveryDays = z
  .string()
  .trim()
  .regex(/^\d+$/, 'shipping:validation.daysWhole')
  .refine((value) => Number(value) <= MAX_DELIVERY_DAYS, 'shipping:validation.daysTooMany')

export const methodSchema = z
  .object({
    name,
    price: z.string().trim().regex(PRICE_PATTERN, 'shipping:validation.price'),
    min_delivery_days: deliveryDays,
    max_delivery_days: deliveryDays,
    is_active: z.boolean(),
  })
  .refine((values) => Number(values.max_delivery_days) >= Number(values.min_delivery_days), {
    path: ['max_delivery_days'],
    message: 'shipping:validation.daysOrder',
  })

export type MethodFormValues = z.infer<typeof methodSchema>

export function methodFormValues(method: ShippingMethod | null): MethodFormValues {
  return {
    name: method?.name ?? '',
    price: method ? String(method.price) : '',
    min_delivery_days: String(method?.min_delivery_days ?? 2),
    max_delivery_days: String(method?.max_delivery_days ?? 4),
    is_active: method?.is_active ?? true,
  }
}

/** The price stays the string the User typed; the API keeps it as a decimal. */
export function toMethodInput(values: MethodFormValues): ShippingMethodInput {
  return {
    name: values.name,
    price: values.price,
    min_delivery_days: Number(values.min_delivery_days),
    max_delivery_days: Number(values.max_delivery_days),
    is_active: values.is_active,
  }
}

/* Carrier ------------------------------------------------------------------------ */

/** Where a Shipment's tracking number goes in a Carrier's tracking link (Carrier::TRACKING_PLACEHOLDER). */
export const TRACKING_PLACEHOLDER = '{tracking_number}'

/** A tracking link template as the API accepts it: an http(s) address holding the placeholder. */
export function isTrackingTemplate(template: string): boolean {
  if (!template.includes(TRACKING_PLACEHOLDER)) return false
  try {
    const url = new URL(template.replaceAll(TRACKING_PLACEHOLDER, 'AB123'))
    return url.protocol === 'https:' || url.protocol === 'http:'
  } catch {
    return false
  }
}

/** The tracking page for a number, as Shipments will link to it. */
export function trackingUrl(template: string, trackingNumber: string): string {
  return template.replaceAll(TRACKING_PLACEHOLDER, encodeURIComponent(trackingNumber))
}

export const carrierSchema = z.object({
  name,
  tracking_url_template: z
    .string()
    .trim()
    .max(500, 'shipping:validation.trackingTooLong')
    .refine((value) => value === '' || isTrackingTemplate(value), 'shipping:validation.tracking'),
})

export type CarrierFormValues = z.infer<typeof carrierSchema>

export function carrierFormValues(carrier: Carrier | null): CarrierFormValues {
  return {
    name: carrier?.name ?? '',
    tracking_url_template: carrier?.tracking_url_template ?? '',
  }
}
