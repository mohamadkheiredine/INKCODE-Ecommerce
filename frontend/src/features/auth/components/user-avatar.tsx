import { Avatar, AvatarFallback } from '@/components/ui/avatar'
import { cn } from '@/lib/utils'

/** Up to two capital initials ("Ahmed Ali" → "AA"). */
function initials(name: string): string {
  return name
    .split(/\s+/)
    .filter(Boolean)
    .slice(0, 2)
    .map((part) => part[0]?.toUpperCase())
    .join('')
}

type UserAvatarProps = { name: string; className?: string; fallbackClassName?: string }

/** A User's initials on the brand gradient; Users have no photos. */
export function UserAvatar({ name, className, fallbackClassName }: UserAvatarProps) {
  return (
    <Avatar className={className}>
      <AvatarFallback
        className={cn('bg-brand-gradient font-semibold text-brand-foreground', fallbackClassName)}
      >
        {initials(name)}
      </AvatarFallback>
    </Avatar>
  )
}
