import { z } from 'zod'
import { COUNTRY_CODES } from '@/lib/countries'
import type { AddressInput, CustomerAddress } from '../types'
import { optionalPhone } from './customer-schema'

const required = (max: number) =>
  z.string().trim().min(1, 'customers:validation.required').max(max, 'customers:validation.tooLong')

const optional = (max: number) => z.string().trim().max(max, 'customers:validation.tooLong')

/** The API's address rules (StoreCustomerAddressRequest). */
export const addressSchema = z.object({
  label: required(50),
  recipient_name: required(120),
  phone: optionalPhone,
  country_code: z
    .string()
    .refine(
      (value) => (COUNTRY_CODES as readonly string[]).includes(value),
      'customers:validation.countryRequired',
    ),
  city: required(100),
  line1: required(150),
  line2: optional(150),
  postal_code: optional(20),
  is_default_billing: z.boolean(),
  is_default_shipping: z.boolean(),
})

export type AddressFormValues = z.infer<typeof addressSchema>

/** A new address starts in the country of the Customer's default address, if any. */
export function addressFormValues(
  address: CustomerAddress | null,
  defaultCountry = '',
): AddressFormValues {
  return {
    label: address?.label ?? '',
    recipient_name: address?.recipient_name ?? '',
    phone: address?.phone ?? '',
    country_code: address?.country_code ?? defaultCountry,
    city: address?.city ?? '',
    line1: address?.line1 ?? '',
    line2: address?.line2 ?? '',
    postal_code: address?.postal_code ?? '',
    is_default_billing: address?.is_default_billing ?? false,
    is_default_shipping: address?.is_default_shipping ?? false,
  }
}

/**
 * The form as the API wants it. A default flag is only sent to take a default over: the
 * API refuses to simply switch one off, and the form never offers that.
 */
export function toAddressInput(values: AddressFormValues): AddressInput {
  const { is_default_billing, is_default_shipping, ...fields } = values
  return {
    ...fields,
    phone: fields.phone || null,
    line2: fields.line2 || null,
    postal_code: fields.postal_code || null,
    ...(is_default_billing && { is_default_billing: true }),
    ...(is_default_shipping && { is_default_shipping: true }),
  }
}

/** An existing address as the API wants it, made the default for one purpose. */
export function makeDefaultInput(
  address: CustomerAddress,
  purpose: 'billing' | 'shipping',
): AddressInput {
  return { ...toAddressInput(addressFormValues(address)), [`is_default_${purpose}`]: true }
}
