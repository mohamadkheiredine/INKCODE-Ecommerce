import { http } from '@/lib/api/client'
import { type ListPage, type ListQuery, toApiParams } from '@/lib/list-query'
import type {
  BulkAction,
  BulkResult,
  Product,
  ProductInput,
  ProductListItem,
  VariantInput,
} from '../types'

export const productKeys = {
  all: ['products'] as const,
  lists: () => [...productKeys.all, 'list'] as const,
  list: (query: ListQuery) => [...productKeys.lists(), query] as const,
  detail: (id: number) => [...productKeys.all, 'detail', id] as const,
}

/** Every change to one Product answers with the whole Product, as `show` does. */
type One = { data: Product }

export const productsApi = {
  async list(query: ListQuery): Promise<ListPage<ProductListItem>> {
    const { data } = await http.get<ListPage<ProductListItem>>('/products', {
      params: toApiParams(query),
    })
    return data
  },

  async get(id: number): Promise<Product> {
    const { data } = await http.get<One>(`/products/${id}`)
    return data.data
  },

  async create(input: ProductInput): Promise<Product> {
    const { data } = await http.post<One>('/products', input)
    return data.data
  },

  async update(id: number, input: ProductInput): Promise<Product> {
    const { data } = await http.put<One>(`/products/${id}`, input)
    return data.data
  },

  async remove(id: number): Promise<void> {
    await http.delete(`/products/${id}`)
  },

  async bulk(action: BulkAction, productIds: number[]): Promise<BulkResult> {
    const { data } = await http.post<{ data: BulkResult }>('/products/bulk', {
      action,
      product_ids: productIds,
    })
    return data.data
  },

  /** Creates the missing combinations of the chosen values; `generated` counts the new Variants. */
  async generateVariants(
    id: number,
    valueIds: number[],
  ): Promise<{ product: Product; generated: number }> {
    const { data } = await http.post<One & { meta: { generated: number } }>(
      `/products/${id}/variants/generate`,
      { attribute_value_ids: valueIds },
    )
    return { product: data.data, generated: data.meta.generated }
  },

  async updateVariants(id: number, variants: VariantInput[]): Promise<Product> {
    const { data } = await http.put<One>(`/products/${id}/variants`, { variants })
    return data.data
  },

  async removeVariant(id: number, variantId: number): Promise<void> {
    await http.delete(`/products/${id}/variants/${variantId}`)
  },

  /** Adds images (multipart, on their own endpoint) after the existing ones. */
  async addImages(id: number, files: File[]): Promise<Product> {
    const body = new FormData()
    files.forEach((file) => body.append('images[]', file))
    const { data } = await http.post<One>(`/products/${id}/images`, body)
    return data.data
  },

  async reorderImages(id: number, imageIds: number[]): Promise<Product> {
    const { data } = await http.put<One>(`/products/${id}/images/order`, { image_ids: imageIds })
    return data.data
  },

  async removeImage(id: number, imageId: number): Promise<Product> {
    const { data } = await http.delete<One>(`/products/${id}/images/${imageId}`)
    return data.data
  },
}
