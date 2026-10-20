import { UsersIcon } from 'lucide-react'
import type { NavEntry } from '@/lib/navigation'

/** Roles that serve Customers may look (customers.view); changing them is gated per action. */
export const customersNav: NavEntry = {
  page: 'customers',
  group: 'sales',
  to: '/customers',
  icon: UsersIcon,
  permission: 'customers.view',
}
