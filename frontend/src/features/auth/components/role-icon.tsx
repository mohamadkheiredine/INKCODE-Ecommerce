import {
  BoxesIcon,
  EyeIcon,
  HeadsetIcon,
  LandmarkIcon,
  type LucideIcon,
  ShieldCheckIcon,
  ShoppingBagIcon,
  WorkflowIcon,
} from 'lucide-react'
import type { RoleKey } from '../types'

export const ROLE_ICONS: Record<RoleKey, LucideIcon> = {
  admin: ShieldCheckIcon,
  operations: WorkflowIcon,
  inventory: BoxesIcon,
  sales: ShoppingBagIcon,
  support: HeadsetIcon,
  finance: LandmarkIcon,
  viewer: EyeIcon,
}
