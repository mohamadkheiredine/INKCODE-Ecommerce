import type { LucideIcon } from 'lucide-react'
import type { Permission } from '@/lib/permissions'
import type enCommon from '@/locales/en/common.json'

/** The sidebar's groups, in the order they appear. */
export const NAV_GROUPS = [
  'dashboard',
  'sales',
  'catalog',
  'operations',
  'promotions',
  'insights',
  'admin',
] as const
export type NavGroup = (typeof NAV_GROUPS)[number]

/** A page's name (common `pages.*`): its menu label, breadcrumb and document title. */
export type PageKey = keyof (typeof enCommon)['pages']

/** Metadata a route carries in its `handle`; breadcrumbs are built from it. */
export type RouteHandle = { crumb?: PageKey }

/** What a module registers to appear in the sidebar. */
export type NavEntry = {
  page: PageKey
  group: NavGroup
  to: string
  icon: LucideIcon
  /** The permission the module needs; left out when every signed-in User may use it. */
  permission?: Permission
}

export type VisibleNavGroup = { group: NavGroup; entries: NavEntry[] }

/** The entries a User may use, grouped in sidebar order; groups left empty are dropped. */
export function visibleNavigation(
  entries: readonly NavEntry[],
  can: (permission: Permission) => boolean,
): VisibleNavGroup[] {
  return NAV_GROUPS.map((group) => ({
    group,
    entries: entries.filter(
      (entry) => entry.group === group && (!entry.permission || can(entry.permission)),
    ),
  })).filter(({ entries: groupEntries }) => groupEntries.length > 0)
}
