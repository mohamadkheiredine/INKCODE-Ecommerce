import { keepPreviousData, useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import type { ListQuery } from '@/lib/list-query'
import { attributeKeys, attributesApi } from '../api/attributes-api'
import type { AttributeInput } from '../types'

/** A page of the Attribute library; the previous page stays on screen while the next one loads. */
export function useAttributes(query: ListQuery) {
  return useQuery({
    queryKey: attributeKeys.list(query),
    queryFn: () => attributesApi.list(query),
    placeholderData: keepPreviousData,
  })
}

/** The whole library, for the Variant builder. */
export function useAttributeLibrary() {
  return useQuery({ queryKey: attributeKeys.library(), queryFn: () => attributesApi.all() })
}

function useInvalidateAttributes() {
  const queryClient = useQueryClient()
  return () => queryClient.invalidateQueries({ queryKey: attributeKeys.all })
}

export function useSaveAttribute() {
  const invalidate = useInvalidateAttributes()
  return useMutation({
    mutationFn: ({ id, input }: { id: number | null; input: AttributeInput }) =>
      id === null ? attributesApi.create(input) : attributesApi.update(id, input),
    onSuccess: invalidate,
  })
}

export function useDeleteAttribute() {
  const invalidate = useInvalidateAttributes()
  return useMutation({
    mutationFn: (id: number) => attributesApi.remove(id),
    onSuccess: invalidate,
  })
}
