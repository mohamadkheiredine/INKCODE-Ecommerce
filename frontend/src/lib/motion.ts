import { useGSAP } from '@gsap/react'
import gsap from 'gsap'
import type { RefObject } from 'react'

gsap.registerPlugin(useGSAP)

export type EntranceStep = {
  /** A selector inside the scope, for example '.js-reveal'. */
  targets: string
  y?: number
  stagger?: number
  delay?: number
  duration?: number
}

/**
 * The project's one entrance motion: a short, staggered fade-up with `power2.out`.
 * Skipped entirely when the User prefers reduced motion; cleaned up on unmount.
 */
export function useEntranceAnimation(scope: RefObject<HTMLElement | null>, steps: EntranceStep[]) {
  useGSAP(
    () => {
      const mm = gsap.matchMedia()
      mm.add('(prefers-reduced-motion: no-preference)', () => {
        for (const { targets, y = 16, stagger = 0, delay = 0, duration = 0.5 } of steps) {
          gsap.from(targets, { y, autoAlpha: 0, duration, delay, stagger, ease: 'power2.out' })
        }
      })
      return () => mm.revert()
    },
    { scope },
  )
}
