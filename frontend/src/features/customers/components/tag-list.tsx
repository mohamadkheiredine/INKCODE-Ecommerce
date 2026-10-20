import { Badge } from '@/components/ui/badge'
import { cn } from '@/lib/utils'
import type { Tag } from '../types'

/** A Customer's Tags as chips; nothing when there are none. */
export function TagList({ tags, className }: { tags: Tag[]; className?: string }) {
  if (tags.length === 0) return null

  return (
    <ul className={cn('flex flex-wrap gap-1.5', className)}>
      {tags.map((tag) => (
        <li key={tag.id}>
          <Badge variant="secondary" className="rounded-md font-normal" dir="auto">
            {tag.name}
          </Badge>
        </li>
      ))}
    </ul>
  )
}
