import {
  type LucideIcon,
  PackagePlusIcon,
  ShoppingCartIcon,
  TruckIcon,
  UserPlusIcon,
} from 'lucide-react'
import type { Permission } from '@/lib/permissions'

export type QuickAction = {
  id: 'newOrder' | 'newProduct' | 'newCustomer' | 'receiveStock'
  to: string
  icon: LucideIcon
  /** Shown only to Roles holding it; the API checks it again. */
  permission: Permission
}

/** The "+" menu and the search palette's shortcuts, each opening its form. */
export const QUICK_ACTIONS: QuickAction[] = [
  { id: 'newOrder', to: '/orders/new', icon: ShoppingCartIcon, permission: 'orders.create' },
  {
    id: 'newProduct',
    to: '/catalog/products/new',
    icon: PackagePlusIcon,
    permission: 'catalog.manage',
  },
  { id: 'newCustomer', to: '/customers?new=1', icon: UserPlusIcon, permission: 'customers.manage' },
  {
    id: 'receiveStock',
    to: '/inventory/receipts/new',
    icon: TruckIcon,
    permission: 'inventory.receive',
  },
]
