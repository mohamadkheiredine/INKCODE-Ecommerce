import gsap from 'gsap'
import { type LenisRef, ReactLenis } from 'lenis/react'
import 'lenis/dist/lenis.css'
import { useEffect, useRef } from 'react'
import { usePrefersReducedMotion } from '@/lib/motion'

/**
 * Lenis smooth scrolling for the page, mounted once at the root and driven by GSAP's
 * ticker, so animations and scrolling share one frame loop. Off under reduced motion.
 * Inner scroll areas opt out with `data-lenis-prevent`.
 */
export function SmoothScroll() {
  const reducedMotion = usePrefersReducedMotion()
  return reducedMotion ? null : <LenisRoot />
}

function LenisRoot() {
  const lenisRef = useRef<LenisRef>(null)

  useEffect(() => {
    const update = (time: number) => lenisRef.current?.lenis?.raf(time * 1000)
    gsap.ticker.add(update)

    // A dialog, sheet or menu locks the page (Radix marks <body> with data-scroll-locked);
    // Lenis would still move the page behind it, so it pauses until the lock is gone.
    const body = document.body
    const followScrollLock = () => {
      const lenis = lenisRef.current?.lenis
      if (body.hasAttribute('data-scroll-locked')) lenis?.stop()
      else lenis?.start()
    }
    const observer = new MutationObserver(followScrollLock)
    observer.observe(body, { attributes: true, attributeFilter: ['data-scroll-locked'] })

    return () => {
      gsap.ticker.remove(update)
      observer.disconnect()
    }
  }, [])

  return <ReactLenis root options={{ autoRaf: false }} ref={lenisRef} />
}
