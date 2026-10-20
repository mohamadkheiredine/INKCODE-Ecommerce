/** A free label on Customers ("wholesale"); new ones are made by typing them. */
export type Tag = { id: number; name: string }

/** One of a Customer's addresses; `country_code` is ISO 3166-1 alpha-2 ("LB"). */
export type CustomerAddress = {
  id: number
  label: string
  recipient_name: string
  phone: string | null
  country_code: string
  city: string
  line1: string
  line2: string | null
  postal_code: string | null
  is_default_billing: boolean
  is_default_shipping: boolean
}

/** A Customer as the list sends it (with Tags); the profile also has the addresses. */
export type Customer = {
  id: number
  name: string
  email: string | null
  phone: string | null
  tags: Tag[]
  addresses?: CustomerAddress[]
  created_at: string
  updated_at: string
}

export type CustomerProfile = Customer & { addresses: CustomerAddress[] }

/** An internal note; `author` is null once the User who wrote it is gone. */
export type CustomerNote = {
  id: number
  body: string
  author: { id: number; name: string } | null
  created_at: string
}

/** What the Customer form sends. Tags go by name; unknown ones are created. */
export type CustomerInput = {
  name: string
  email: string | null
  phone: string | null
  tags: string[]
}

/** What the address form sends; a flag set to true makes the address that default. */
export type AddressInput = Omit<
  CustomerAddress,
  'id' | 'is_default_billing' | 'is_default_shipping'
> & {
  is_default_billing?: boolean
  is_default_shipping?: boolean
}

/** What an address is used for; a Customer has one default address of each. */
export type AddressPurpose = 'billing' | 'shipping'
