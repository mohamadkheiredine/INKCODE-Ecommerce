import { HistoryIcon } from 'lucide-react'
import type { NavEntry } from '@/lib/navigation'

/** Only Roles with audit.view (the Admin) read the audit log. */
export const auditLogNav: NavEntry = {
  page: 'auditLog',
  group: 'admin',
  to: '/audit-log',
  icon: HistoryIcon,
  permission: 'audit.view',
}
