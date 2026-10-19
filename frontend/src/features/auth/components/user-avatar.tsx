import { Avatar, AvatarFallback } from '@/components/ui/avatar'
import { cn, initials } from '@/lib/utils'

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
