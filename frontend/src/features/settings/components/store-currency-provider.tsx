import type { ReactNode } from 'react'
import { CurrencyContext, DEFAULT_CURRENCY } from '@/lib/format'
import { useStoreProfile } from '../hooks/use-settings'

/** Makes every amount inside it use the Store currency chosen in Settings. */
export function StoreCurrencyProvider({ children }: { children: ReactNode }) {
  const { data: store } = useStoreProfile()

  return <CurrencyContext value={store?.currency ?? DEFAULT_CURRENCY}>{children}</CurrencyContext>
}
