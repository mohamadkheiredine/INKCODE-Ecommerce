import { RotateCcwIcon } from 'lucide-react'
import type { NavEntry } from '@/lib/navigation'

/** Every Role that sees Returns; each step is gated by its own permission. */
export const returnsNav: NavEntry = {
  page: 'returns',
  group: 'sales',
  to: '/returns',
  icon: RotateCcwIcon,
  permission: 'returns.view',
}
