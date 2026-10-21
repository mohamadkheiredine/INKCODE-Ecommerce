import { WalletIcon } from 'lucide-react'
import type { NavEntry } from '@/lib/navigation'

/** Every Role that sees Payment ledgers; recording and refunding are gated per action. */
export const paymentsNav: NavEntry = {
  page: 'payments',
  group: 'sales',
  to: '/payments',
  icon: WalletIcon,
  permission: 'payments.view',
}
