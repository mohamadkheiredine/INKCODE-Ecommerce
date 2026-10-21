import { FileChartColumnIcon } from 'lucide-react'
import type { NavEntry } from '@/lib/navigation'

/** Roles with reports.view open the six reports; exporting needs reports.export too. */
export const reportsNav: NavEntry = {
  page: 'reports',
  group: 'insights',
  to: '/reports',
  icon: FileChartColumnIcon,
  permission: 'reports.view',
}
