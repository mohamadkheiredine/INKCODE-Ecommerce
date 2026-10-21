import { useCan } from '@/features/auth/hooks/use-can'
import { QUICK_ACTIONS, type QuickAction } from '../quick-actions'

/** The quick actions the signed-in User's Role may take. */
export function useQuickActions(): QuickAction[] {
  const can = useCan()
  return QUICK_ACTIONS.filter((action) => can(action.permission))
}
