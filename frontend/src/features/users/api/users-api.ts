import { http } from '@/lib/api/client'
import { type ListPage, type ListQuery, toApiParams } from '@/lib/list-query'
import type { RolePermissions, User, UserInput } from '../types'

export const userKeys = {
  all: ['users'] as const,
  lists: () => [...userKeys.all, 'list'] as const,
  list: (query: ListQuery) => [...userKeys.lists(), query] as const,
  roles: () => ['roles'] as const,
}

export const usersApi = {
  async list(query: ListQuery): Promise<ListPage<User>> {
    const { data } = await http.get<ListPage<User>>('/users', { params: toApiParams(query) })
    return data
  },

  async create(input: UserInput): Promise<User> {
    const { data } = await http.post<{ data: User }>('/users', input)
    return data.data
  },

  async update(id: number, input: UserInput): Promise<User> {
    const { data } = await http.put<{ data: User }>(`/users/${id}`, input)
    return data.data
  },

  async setActive(id: number, active: boolean): Promise<User> {
    const { data } = await http.post<{ data: User }>(
      `/users/${id}/${active ? 'reactivate' : 'deactivate'}`,
    )
    return data.data
  },

  async roles(): Promise<RolePermissions[]> {
    const { data } = await http.get<{ data: RolePermissions[] }>('/roles')
    return data.data
  },
}
