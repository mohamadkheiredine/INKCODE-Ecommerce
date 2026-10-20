import {
  ArrowLeftRightIcon,
  ArrowRightLeftIcon,
  BoxesIcon,
  PackagePlusIcon,
  WarehouseIcon,
} from 'lucide-react'
import type { NavEntry } from '@/lib/navigation'

/**
 * Every Role that sees stock (inventory.view) may look at these screens; receiving and
 * managing Warehouses need their own permissions, gated per action.
 */
export const stockLevelsNav: NavEntry = {
  page: 'stockLevels',
  group: 'operations',
  to: '/inventory/stock',
  icon: BoxesIcon,
  permission: 'inventory.view',
}

export const receiptsNav: NavEntry = {
  page: 'receipts',
  group: 'operations',
  to: '/inventory/receipts',
  icon: PackagePlusIcon,
  permission: 'inventory.view',
}

export const stockMovementsNav: NavEntry = {
  page: 'stockMovements',
  group: 'operations',
  to: '/inventory/movements',
  icon: ArrowRightLeftIcon,
  permission: 'inventory.view',
}

export const warehousesNav: NavEntry = {
  page: 'warehouses',
  group: 'operations',
  to: '/inventory/warehouses',
  icon: WarehouseIcon,
  permission: 'inventory.view',
}

export const transfersNav: NavEntry = {
  page: 'transfers',
  group: 'operations',
  to: '/inventory/transfers',
  icon: ArrowLeftRightIcon,
  permission: 'inventory.view',
}
