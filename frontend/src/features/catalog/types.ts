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
