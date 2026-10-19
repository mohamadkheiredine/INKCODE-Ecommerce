import { HouseIcon } from 'lucide-react'
import { describe, expect, it } from 'vitest'
import type { Permission } from '@/lib/permissions'
import { type NavEntry, visibleNavigation } from './navigation'

const entry = (to: string, group: NavEntry['group'], permission?: Permission): NavEntry => ({
  page: 'home',
  group,
  to,
  icon: HouseIcon,
  permission,
})

const registry = [
  entry('/settings', 'admin', 'settings.manage'),
  entry('/', 'dashboard'),
  entry('/orders', 'sales', 'orders.view'),
  entry('/returns', 'sales', 'returns.view'),
  entry('/coupons', 'promotions', 'coupons.manage'),
]

const holding =
  (...permissions: Permission[]) =>
  (permission: Permission) =>
    permissions.includes(permission)

describe('The sidebar registry', () => {
  it('shows only the entries the Role holds a permission for, in sidebar group order', () => {
    const groups = visibleNavigation(registry, holding('orders.view', 'settings.manage'))

    expect(groups.map(({ group, entries }) => [group, entries.map((e) => e.to)])).toEqual([
      ['dashboard', ['/']],
      ['sales', ['/orders']],
      ['admin', ['/settings']],
    ])
  })

  it('hides a group when none of its entries may be used', () => {
    const groups = visibleNavigation(registry, holding())

    expect(groups.map(({ group }) => group)).toEqual(['dashboard'])
  })
})
