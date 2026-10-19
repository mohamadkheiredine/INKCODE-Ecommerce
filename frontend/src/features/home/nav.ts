import { HouseIcon } from 'lucide-react'
import type { NavEntry } from '@/lib/navigation'

/** The landing page greets every signed-in User, so it needs no permission. */
export const homeNav: NavEntry = { page: 'home', group: 'dashboard', to: '/', icon: HouseIcon }
