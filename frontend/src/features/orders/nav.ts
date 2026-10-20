import { ShoppingCartIcon } from 'lucide-react'
import type { NavEntry } from '@/lib/navigation'

/** Every Role that sees Orders; creating, confirming and cancelling are gated per action. */
export const ordersNav: NavEntry = {
  page: 'orders',
  group: 'sales',
  to: '/orders',
  icon: ShoppingCartIcon,
  permission: 'orders.view',
}
