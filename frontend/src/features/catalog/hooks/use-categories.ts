import { keepPreviousData, useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import type { ListQuery } from '@/lib/list-query'
import { categoriesApi, categoryKeys } from '../api/categories-api'
import type { CategoryInput } from '../types'

/** A page of Categories; the previous page stays on screen while the next one loads. */
export function useCategories(query: ListQuery) {
  return useQuery({
    queryKey: categoryKeys.list(query),
    queryFn: () => categoriesApi.list(query),
    placeholderData: keepPreviousData,
  })
}

/** The top-level Categories a Category can be placed under. */
export function useParentCategories() {
  return useQuery({
    queryKey: categoryKeys.parents(),
    queryFn: () => categoriesApi.topLevel(),
  })
}

/** After any change, every Category list (and the parent picker) asks again. */
function useInvalidateCategories() {
  const queryClient = useQueryClient()
  return () => queryClient.invalidateQueries({ queryKey: categoryKeys.all })
}

export function useSaveCategory() {
  const invalidate = useInvalidateCategories()
  return useMutation({
    mutationFn: ({ id, input }: { id: number | null; input: CategoryInput }) =>
      id === null ? categoriesApi.create(input) : categoriesApi.update(id, input),
    onSuccess: invalidate,
  })
}

export function useDeleteCategory() {
  const invalidate = useInvalidateCategories()
  return useMutation({
    mutationFn: (id: number) => categoriesApi.remove(id),
    onSuccess: invalidate,
  })
}
