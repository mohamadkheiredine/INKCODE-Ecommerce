/** A Category as the API sends it. A child's `parent` is its top-level Category. */
export type Category = {
  id: number
  name: string
  slug: string
  description: string | null
  parent: { id: number; name: string; slug: string } | null
  children_count: number
  created_at: string
  updated_at: string
}

/** What the Category form sends; an empty slug is generated from the name by the API. */
export type CategoryInput = {
  name: string
  slug: string
  parent_id: number | null
  description: string
}

export type Brand = {
  id: number
  name: string
  slug: string
  logo_url: string | null
  created_at: string
  updated_at: string
}

/** What the Brand form sends; the logo travels separately (multipart). */
export type BrandInput = {
  name: string
  slug: string
}

/** What happens to a Brand's logo when the Brand is saved. */
export type LogoChange = { kind: 'keep' } | { kind: 'replace'; file: File } | { kind: 'remove' }

/** One value of an Attribute; its code is what generated SKUs are made of ("RED"). */
export type AttributeValue = { id: number; name: string; code: string }

export type Attribute = {
  id: number
  name: string
  values: AttributeValue[]
  created_at: string
  updated_at: string
}

/** The whole list of values: one with an id is kept, one without is new, one left out is removed. */
export type AttributeInput = {
  name: string
  values: { id: number | null; name: string; code: string }[]
}

export const PRODUCT_STATUSES = ['draft', 'active', 'archived'] as const
export type ProductStatus = (typeof PRODUCT_STATUSES)[number]

type CatalogRef = { id: number; name: string; slug: string }

/** A decimal string from the API ("19.90"), never a float. */
type Decimal = `${number}`

/** Amounts are without VAT. */
type ProductFields = {
  id: number
  name: string
  slug: string
  description: string | null
  category: CatalogRef
  brand: CatalogRef | null
  base_price: Decimal
  taxable: boolean
  tags: string[]
  seo_title: string | null
  seo_description: string | null
  status: ProductStatus
  created_at: string
  updated_at: string
}

/** A row of the Products list; the price range is null while it has no Variants. */
export type ProductListItem = ProductFields & {
  main_image_url: string | null
  price_min: Decimal | null
  price_max: Decimal | null
  variants_count: number
}

export type Variant = {
  id: number
  sku: string
  price: Decimal
  average_cost: Decimal
  /** Null follows the store's default threshold. */
  low_stock_threshold: number | null
  options: { attribute: string; value: string }[]
  value_ids: number[]
  /** Its stock per Warehouse that ever held it; only sent to Roles that see stock. */
  stock?: {
    warehouse: { id: number; name: string; code: string }
    on_hand: number
    reserved: number
    available: number
  }[]
}

/** One Product with everything its screen shows. */
export type Product = ProductFields & {
  images: { id: number; url: string }[]
  /** The Attributes its Variants differ by, with the values they use. */
  attributes: { id: number; name: string; value_ids: number[] }[]
  variants: Variant[]
}

export type ProductInput = {
  name: string
  slug: string
  description: string
  category_id: number
  brand_id: number | null
  base_price: string
  taxable: boolean
  tags: string[]
  seo_title: string
  seo_description: string
  status: ProductStatus
}

export type VariantInput = {
  id: number
  sku: string
  price: string
  average_cost: string
  low_stock_threshold: number | null
}

export const BULK_ACTIONS = ['activate', 'archive', 'delete'] as const
export type BulkAction = (typeof BULK_ACTIONS)[number]

export type BulkResult = {
  changed: number
  deleted: number
  skipped: { id: number; name: string; reason: string }[]
}
