import { Avatar, AvatarFallback } from '@/components/ui/avatar'
import { cn, initials } from '@/lib/utils'

/**
 * A Customer's initials on a soft primary tint, so Customers never look like Users
 * (whose avatars sit on the brand gradient).
 */
export function CustomerAvatar({ name, className }: { name: string; className?: string }) {
  return (
    <Avatar className={cn('size-9', className)}>
      <AvatarFallback className="bg-primary/10 font-semibold text-primary">
        {initials(name)}
      </AvatarFallback>
    </Avatar>
  )
}
