import { Spinner } from '@/components/ui/spinner'

/**
 * A spinner inside a control that already says what is happening ("Signing in…").
 * Hidden from assistive technology so it doesn't change the control's accessible name.
 */
export function InlineSpinner({ className }: { className?: string }) {
  return (
    <Spinner className={className} role={undefined} aria-label={undefined} aria-hidden="true" />
  )
}
