import { keepPreviousData, useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import type { ListQuery } from '@/lib/list-query'
import { brandKeys, brandsApi } from '../api/brands-api'
import type { Brand, BrandInput } from '../types'

/** A page of Brands; the previous page stays on screen while the next one loads. */
export function useBrands(query: ListQuery) {
  return useQuery({
    queryKey: brandKeys.list(query),
    queryFn: () => brandsApi.list(query),
    placeholderData: keepPreviousData,
  })
}

/** What happens to the logo when a Brand is saved. */
export type LogoChange = { kind: 'keep' } | { kind: 'replace'; file: File } | { kind: 'remove' }

type SaveBrand = { id: number | null; input: BrandInput; logo: LogoChange }

/** The saved Brand, and the reason its logo change failed, if it did. */
export type SavedBrand = { brand: Brand; logoError: unknown }

/**
 * Saves a Brand, then its logo change. A failing logo never undoes the saved Brand:
 * the result says what went wrong so the screen can tell the User.
 */
export function useSaveBrand() {
  const queryClient = useQueryClient()
  return useMutation({
    mutationFn: async ({ id, input, logo }: SaveBrand): Promise<SavedBrand> => {
      const brand = id === null ? await brandsApi.create(input) : await brandsApi.update(id, input)
      try {
        if (logo.kind === 'replace')
          return { brand: await brandsApi.uploadLogo(brand.id, logo.file), logoError: null }
        if (logo.kind === 'remove')
          return { brand: await brandsApi.removeLogo(brand.id), logoError: null }
      } catch (logoError) {
        return { brand, logoError }
      }
      return { brand, logoError: null }
    },
    onSettled: () => queryClient.invalidateQueries({ queryKey: brandKeys.all }),
  })
}

export function useDeleteBrand() {
  const queryClient = useQueryClient()
  return useMutation({
    mutationFn: (id: number) => brandsApi.remove(id),
    onSuccess: () => queryClient.invalidateQueries({ queryKey: brandKeys.all }),
  })
}
