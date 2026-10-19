import type { LucideIcon } from 'lucide-react'
import type { ReactNode } from 'react'
import {
  Empty,
  EmptyContent,
  EmptyDescription,
  EmptyHeader,
  EmptyMedia,
  EmptyTitle,
} from '@/components/ui/empty'
import { cn } from '@/lib/utils'

type EmptyStateProps = {
  icon: LucideIcon
  title: string
  description: string
  /** The next step, so an empty screen is never a dead end (a button or a link). */
  action: ReactNode
  className?: string
}

/** What a screen shows when there is nothing to list yet: what is missing, and what to do next. */
export function EmptyState({ icon: Icon, title, description, action, className }: EmptyStateProps) {
  return (
    <Empty className={cn('border border-dashed bg-card/60', className)}>
      <EmptyHeader>
        <EmptyMedia variant="icon" className="bg-primary/10 text-primary">
          <Icon aria-hidden="true" />
        </EmptyMedia>
        <EmptyTitle>{title}</EmptyTitle>
        <EmptyDescription>{description}</EmptyDescription>
      </EmptyHeader>
      <EmptyContent>{action}</EmptyContent>
    </Empty>
  )
}
