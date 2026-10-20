import { MapPinnedIcon, TruckIcon } from 'lucide-react'
import type { NavEntry } from '@/lib/navigation'

/**
 * Every Role that sees Orders may look at where the store ships (orders.view, as the
 * API's Policies allow); changing it needs shipping.manage, gated per action.
 */
export const shippingZonesNav: NavEntry = {
  page: 'shippingZones',
  group: 'operations',
  to: '/shipping/zones',
  icon: MapPinnedIcon,
  permission: 'orders.view',
}

export const carriersNav: NavEntry = {
  page: 'carriers',
  group: 'operations',
  to: '/shipping/carriers',
  icon: TruckIcon,
  permission: 'orders.view',
}
