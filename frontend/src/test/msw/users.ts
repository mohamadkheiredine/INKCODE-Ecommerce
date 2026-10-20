import { HttpResponse, http } from 'msw/http'
import type { RolePermissions, User, UserInput } from '@/features/users/types'
import { apiError } from './handlers'
import { listResponse } from './lists'
import { server } from './server'

let nextId = 900

export function aUser(overrides: Partial<User> = {}): User {
  const id = overrides.id ?? nextId++
  return {
    id,
    name: `User ${id}`,
    email: `user${id}@inkcode.test`,
    role: 'sales',
    is_active: true,
    created_at: '2026-10-01T09:00:00+00:00',
    updated_at: '2026-10-01T09:00:00+00:00',
    ...overrides,
  }
}

/**
 * The Users API, kept in memory: the list convention (search, Role and status filters),
 * 422 for a taken email, deactivation and reactivation.
 */
export function userStore(initial: User[] = []) {
  const users = [...initial]
  const bodies: UserInput[] = []
  const find = (id: unknown) => users.find((user) => user.id === Number(id))

  server.use(
    http.get('*/api/v1/users', ({ request }) =>
      listResponse<User>(users, request, {
        searchIn: ['name', 'email'],
        defaultSort: 'name',
        filters: {
          role: (row, value) => row.role === value,
          status: (row, value) => row.is_active === (value === 'active'),
        },
      }),
    ),
    http.post('*/api/v1/users', async ({ request }) => {
      const body = (await request.json()) as UserInput
      bodies.push(body)
      if (users.some((user) => user.email === body.email)) {
        const message = 'The email has already been taken.'
        return apiError(422, 'validation_failed', message, { email: [message] })
      }
      const user = aUser({ name: body.name, email: body.email, role: body.role })
      users.push(user)
      return HttpResponse.json({ data: user }, { status: 201 })
    }),
    http.post('*/api/v1/users/:id/:action', ({ params }) => {
      const user = find(params.id)
      if (!user) return apiError(404, 'not_found', 'The requested resource was not found.')
      user.is_active = params.action === 'reactivate'
      return HttpResponse.json({ data: user })
    }),
  )

  return { users, bodies }
}

export function rolesMatrix(roles: RolePermissions[]) {
  server.use(http.get('*/api/v1/roles', () => HttpResponse.json({ data: roles })))
}
