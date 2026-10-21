import { TicketPercentIcon } from 'lucide-react'
import type { NavEntry } from '@/lib/navigation'

/** Roles with coupons.view see Coupons and their usage; changing them needs coupons.manage. */
export const couponsNav: NavEntry = {
  page: 'coupons',
  group: 'promotions',
  to: '/coupons',
  icon: TicketPercentIcon,
  permission: 'coupons.view',
}
