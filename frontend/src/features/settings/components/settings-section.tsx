import type { LucideIcon } from 'lucide-react'
import { type ReactNode, useId } from 'react'
import { Card, CardContent } from '@/components/ui/card'

type SettingsSectionProps = {
  title: string
  description: string
  icon: LucideIcon
  children: ReactNode
}

/**
 * One group of settings: what it is about on the reading side, its fields in a card
 * beside it on wide screens and below it on narrow ones.
 */
export function SettingsSection({
  title,
  description,
  icon: Icon,
  children,
}: SettingsSectionProps) {
  const headingId = useId()

  return (
    <section
      aria-labelledby={headingId}
      className="js-reveal grid gap-4 lg:grid-cols-[minmax(0,17rem)_minmax(0,1fr)] lg:gap-10"
    >
      <div className="flex flex-col gap-2 lg:pt-2">
        <div className="flex items-center gap-2.5">
          <span className="flex size-8 shrink-0 items-center justify-center rounded-lg bg-primary/10 text-primary">
            <Icon className="size-4" aria-hidden="true" />
          </span>
          <h2 id={headingId} className="text-base font-semibold tracking-tight">
            {title}
          </h2>
        </div>
        <p className="text-sm text-muted-foreground">{description}</p>
      </div>
      <Card className="rounded-2xl">
        <CardContent className="flex flex-col gap-5">{children}</CardContent>
      </Card>
    </section>
  )
}
