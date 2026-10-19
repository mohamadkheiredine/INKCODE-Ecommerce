import { SettingsIcon } from 'lucide-react'
import type { NavEntry } from '@/lib/navigation'

/** Only the Admin sees or changes the settings (settings.manage). */
export const settingsNav: NavEntry = {
  page: 'settings',
  group: 'admin',
  to: '/settings',
  icon: SettingsIcon,
  permission: 'settings.manage',
}
