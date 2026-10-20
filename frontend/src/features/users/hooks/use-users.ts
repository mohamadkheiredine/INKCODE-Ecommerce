import { keepPreviousData, useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import type { ListQuery } from '@/lib/list-query'
import { userKeys, usersApi } from '../api/users-api'
import type { UserInput } from '../types'

/** A page of Users; the previous page stays on screen while the next one loads. */
export function useUsers(query: ListQuery) {
  return useQuery({
    queryKey: userKeys.list(query),
    queryFn: () => usersApi.list(query),
    placeholderData: keepPreviousData,
  })
}

/** The Role → permission matrix; fixed in code, so it is fetched once per visit. */
export function useRoles() {
  return useQuery({ queryKey: userKeys.roles(), queryFn: usersApi.roles, staleTime: Infinity })
}

export function useSaveUser() {
  const queryClient = useQueryClient()
  return useMutation({
    mutationFn: ({ id, input }: { id: number | null; input: UserInput }) =>
      id === null ? usersApi.create(input) : usersApi.update(id, input),
    onSuccess: () => queryClient.invalidateQueries({ queryKey: userKeys.all }),
  })
}

export function useSetUserActive() {
  const queryClient = useQueryClient()
  return useMutation({
    mutationFn: ({ id, active }: { id: number; active: boolean }) =>
      usersApi.setActive(id, active),
    onSuccess: () => queryClient.invalidateQueries({ queryKey: userKeys.all }),
  })
}
