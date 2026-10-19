import { type QueryClient, QueryClientProvider } from '@tanstack/react-query'
import type { ReactNode } from 'react'
import { useTranslation } from 'react-i18next'
import { ThemeProvider } from '@/components/theme/theme-provider'
import { DirectionProvider } from '@/components/ui/direction'
import { Toaster } from '@/components/ui/sonner'
import { TooltipProvider } from '@/components/ui/tooltip'
import { SmoothScroll } from './smooth-scroll'

/** Everything a screen needs around it: server state, theme, reading direction, toasts, smooth scrolling. */
export function AppProviders({
  queryClient,
  children,
}: {
  queryClient: QueryClient
  children: ReactNode
}) {
  const { i18n } = useTranslation()
  const dir = i18n.dir(i18n.resolvedLanguage)

  return (
    <QueryClientProvider client={queryClient}>
      <ThemeProvider defaultTheme="light">
        <DirectionProvider dir={dir}>
          <TooltipProvider>
            <SmoothScroll />
            {children}
            {/* Bottom corner on the reading side: toasts never cover the header's controls. */}
            <Toaster
              richColors
              closeButton
              position={dir === 'rtl' ? 'bottom-left' : 'bottom-right'}
              dir={dir}
            />
          </TooltipProvider>
        </DirectionProvider>
      </ThemeProvider>
    </QueryClientProvider>
  )
}
