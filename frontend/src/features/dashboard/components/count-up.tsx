import { useGSAP } from '@gsap/react'
import gsap from 'gsap'
import { useRef } from 'react'
import { usePrefersReducedMotion } from '@/lib/motion'

type CountUpProps = {
  /** The figure to count up to. */
  value: number
  /** How an in-between figure is written while counting. */
  format: (value: number) => string
  /** The exact final text (amounts are formatted from their decimal string, never a float). */
  text: string
}

/** A KPI figure that counts up from zero; shown as is when the User prefers reduced motion. */
export function CountUp({ value, format, text }: CountUpProps) {
  const ref = useRef<HTMLSpanElement>(null)
  const reducedMotion = usePrefersReducedMotion()

  useGSAP(
    () => {
      const element = ref.current
      if (!element) return
      element.textContent = text
      if (reducedMotion || value === 0) return
      const counter = { value: 0 }
      gsap.to(counter, {
        value,
        duration: 0.6,
        ease: 'power2.out',
        onUpdate: () => {
          element.textContent = format(counter.value)
        },
        onComplete: () => {
          element.textContent = text
        },
      })
    },
    { dependencies: [value, text, reducedMotion], scope: ref, revertOnUpdate: true },
  )

  return (
    // Keyed by the text, so a new figure gets a fresh node rather than one GSAP wrote into.
    <span key={text} ref={ref} className="tabular-nums">
      {text}
    </span>
  )
}
