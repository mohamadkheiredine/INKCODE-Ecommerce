import { type ReactNode, useMemo } from 'react'
import { CurrencyContext, DEFAULT_CURRENCY } from '@/lib/format'
import { useStoreProfile } from '../hooks/use-settings'

/**
 * Makes every amount inside it use the Store currency chosen in Settings. The value
 * only changes when the code or symbol does, so screens don't re-render (and tables
 * don't rebuild their columns) when the same store profile is fetched again.
 */
export function StoreCurrencyProvider({ children }: { children: ReactNode }) {
  const { data: store } = useStoreProfile()
  const code = store?.currency.code ?? DEFAULT_CURRENCY.code
  const symbol = store?.currency.symbol ?? DEFAULT_CURRENCY.symbol
  const currency = useMemo(() => ({ code, symbol }), [code, symbol])

  return <CurrencyContext value={currency}>{children}</CurrencyContext>
}
