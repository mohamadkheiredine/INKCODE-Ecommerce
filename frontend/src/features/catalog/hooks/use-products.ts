import { keepPreviousData, useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import type { ListQuery } from '@/lib/list-query'
import { productKeys, productsApi } from '../api/products-api'
import type { BulkAction, Product, ProductInput, VariantInput } from '../types'

/** A page of Products; the previous page stays on screen while the next one loads. */
export function useProducts(query: ListQuery) {
  return useQuery({
    queryKey: productKeys.list(query),
    queryFn: () => productsApi.list(query),
    placeholderData: keepPreviousData,
  })
}

export function useProduct(id: number) {
  return useQuery({ queryKey: productKeys.detail(id), queryFn: () => productsApi.get(id) })
}

/**
 * After a change to one Product: its screen shows the answer straight away, and every
 * list asks again (names, prices and images appear there).
 */
function useProductSaved() {
  const queryClient = useQueryClient()
  return (product: Product) => {
    queryClient.setQueryData(productKeys.detail(product.id), product)
    return queryClient.invalidateQueries({ queryKey: productKeys.lists() })
  }
}

export function useSaveProduct() {
  const saved = useProductSaved()
  return useMutation({
    mutationFn: ({ id, input }: { id: number | null; input: ProductInput }) =>
      id === null ? productsApi.create(input) : productsApi.update(id, input),
    onSuccess: saved,
  })
}

export function useDeleteProduct() {
  const queryClient = useQueryClient()
  return useMutation({
    mutationFn: (id: number) => productsApi.remove(id),
    onSuccess: () => queryClient.invalidateQueries({ queryKey: productKeys.lists() }),
  })
}

export function useBulkProducts() {
  const queryClient = useQueryClient()
  return useMutation({
    mutationFn: ({ action, ids }: { action: BulkAction; ids: number[] }) =>
      productsApi.bulk(action, ids),
    onSettled: () => queryClient.invalidateQueries({ queryKey: productKeys.all }),
  })
}

export function useGenerateVariants(productId: number) {
  const saved = useProductSaved()
  return useMutation({
    mutationFn: (valueIds: number[]) => productsApi.generateVariants(productId, valueIds),
    onSuccess: ({ product }) => saved(product),
  })
}

export function useUpdateVariants(productId: number) {
  const saved = useProductSaved()
  return useMutation({
    mutationFn: (variants: VariantInput[]) => productsApi.updateVariants(productId, variants),
    onSuccess: saved,
  })
}

export function useRemoveVariant(productId: number) {
  const queryClient = useQueryClient()
  return useMutation({
    mutationFn: (variantId: number) => productsApi.removeVariant(productId, variantId),
    onSuccess: () => queryClient.invalidateQueries({ queryKey: productKeys.all }),
  })
}

/** Adding, reordering and removing images: each answers with the saved Product. */
export function useProductImages(productId: number) {
  const saved = useProductSaved()
  const options = { onSuccess: saved }
  return {
    add: useMutation({
      mutationFn: (files: File[]) => productsApi.addImages(productId, files),
      ...options,
    }),
    reorder: useMutation({
      mutationFn: (imageIds: number[]) => productsApi.reorderImages(productId, imageIds),
      ...options,
    }),
    remove: useMutation({
      mutationFn: (imageId: number) => productsApi.removeImage(productId, imageId),
      ...options,
    }),
  }
}
