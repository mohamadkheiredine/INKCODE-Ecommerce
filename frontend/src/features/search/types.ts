import type { ProductStatus } from '@/features/catalog/types'
import type { OrderStatus } from '@/features/orders/types'
import type { Amount } from '@/lib/format'

export type OrderMatch = {
  id: number
  number: string
  status: OrderStatus
  total: Amount
  customer_name: string
  placed_at: string
}

export type ProductMatch = {
  id: number
  name: string
  sku: string | null
  status: ProductStatus
  main_image_url: string | null
}

export type CustomerMatch = {
  id: number
  name: string
  email: string | null
  phone: string | null
}

/** A group is missing when the User's Role may not list that module. */
export type SearchResults = {
  orders?: OrderMatch[]
  products?: ProductMatch[]
  customers?: CustomerMatch[]
}
