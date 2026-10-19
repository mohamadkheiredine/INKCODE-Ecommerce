import '@testing-library/jest-dom/vitest'
import { cleanup, configure } from '@testing-library/react'
import { toast } from 'sonner'
import { afterAll, afterEach, beforeAll, beforeEach } from 'vitest'
import i18n from '@/lib/i18n'
import { server } from './msw/server'

// jsdom has no matchMedia. Report "reduced motion" so GSAP entrance animations
// stay off and elements are visible immediately, as for users who opt out of motion.
Object.defineProperty(window, 'matchMedia', {
  writable: true,
  value: (query: string) => ({
    matches: query.includes('prefers-reduced-motion: reduce'),
    media: query,
    onchange: null,
    addEventListener: () => {},
    removeEventListener: () => {},
    addListener: () => {},
    removeListener: () => {},
    dispatchEvent: () => false,
  }),
})

// jsdom has no layout engine; Radix primitives only need these to exist.
class NoopResizeObserver {
  observe() {}
  unobserve() {}
  disconnect() {}
}
window.ResizeObserver ??= NoopResizeObserver as unknown as typeof ResizeObserver
Element.prototype.scrollIntoView ??= () => {}

// Multi-step flows (sign in, then navigate) can exceed the 1s default on a busy machine.
configure({ asyncUtilTimeout: 3000 })

beforeAll(() => server.listen({ onUnhandledFrame: 'error' }))

beforeEach(async () => {
  localStorage.clear()
  document.documentElement.classList.remove('dark')
  await i18n.changeLanguage('en')
})

afterEach(() => {
  // Sonner keeps toasts in a module-level store; never let one leak into the next test.
  toast.dismiss()
  cleanup()
  server.resetHandlers()
})

afterAll(() => server.close())
