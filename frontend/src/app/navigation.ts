import { homeNav } from '@/features/home/nav'
import type { NavEntry } from '@/lib/navigation'

/**
 * The one sidebar registry: each module adds its entry (with the permission it needs)
 * once the module works end to end, so the menu never shows placeholders.
 */
export const NAV_ENTRIES: readonly NavEntry[] = [homeNav]
