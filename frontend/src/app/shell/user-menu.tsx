import { ChevronDownIcon, LogOutIcon, UserRoundIcon } from 'lucide-react'
import { useTranslation } from 'react-i18next'
import { Link, useNavigate } from 'react-router'
import { toast } from 'sonner'
import { Badge } from '@/components/ui/badge'
import { Button } from '@/components/ui/button'
import {
  DropdownMenu,
  DropdownMenuContent,
  DropdownMenuGroup,
  DropdownMenuItem,
  DropdownMenuLabel,
  DropdownMenuSeparator,
  DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu'
import { useDirection } from '@/components/ui/direction'
import { useSignOut } from '@/features/auth/hooks/use-auth'
import { UserAvatar } from '@/features/auth/components/user-avatar'
import { SIGN_IN_PATH } from '@/features/auth/redirect'
import type { CurrentUser } from '@/features/auth/types'

/** Who is signed in, a way to their profile, and a way out. */
export function UserMenu({ user }: { user: CurrentUser }) {
  const { t } = useTranslation()
  const signOut = useSignOut()
  const navigate = useNavigate()
  const dir = useDirection()

  const handleSignOut = () => {
    signOut.mutate(undefined, {
      onSuccess: () => {
        toast.success(t('auth:signOut.done'))
        void navigate(SIGN_IN_PATH, { replace: true })
      },
      onError: () => toast.error(t('auth:signOut.failed')),
    })
  }

  return (
    <DropdownMenu dir={dir}>
      <DropdownMenuTrigger asChild>
        <Button
          type="button"
          variant="ghost"
          className="h-10 gap-2 rounded-full ps-1 pe-1 lg:pe-3"
          aria-label={t('shell.userMenu')}
        >
          <UserAvatar name={user.name} className="size-8" fallbackClassName="text-xs" />
          <span className="hidden max-w-36 truncate text-sm font-medium lg:inline">
            {user.name}
          </span>
          <ChevronDownIcon
            className="hidden size-4 text-muted-foreground lg:block"
            aria-hidden="true"
          />
        </Button>
      </DropdownMenuTrigger>
      <DropdownMenuContent align="end" className="w-64" data-lenis-prevent>
        <DropdownMenuLabel className="flex flex-col gap-1.5 py-2 font-normal">
          <span className="truncate text-sm font-semibold text-foreground">{user.name}</span>
          <span className="truncate text-xs text-muted-foreground" dir="ltr">
            {user.email}
          </span>
          {user.role && (
            <Badge variant="secondary" className="mt-0.5 w-fit rounded-full">
              {t(`roles.${user.role}`)}
            </Badge>
          )}
        </DropdownMenuLabel>
        <DropdownMenuSeparator />
        <DropdownMenuGroup>
          <DropdownMenuItem asChild>
            <Link to="/profile">
              <UserRoundIcon aria-hidden="true" />
              {t('actions.profile')}
            </Link>
          </DropdownMenuItem>
        </DropdownMenuGroup>
        <DropdownMenuSeparator />
        <DropdownMenuItem
          variant="destructive"
          disabled={signOut.isPending}
          onSelect={handleSignOut}
        >
          <LogOutIcon className="rtl:rotate-180" aria-hidden="true" />
          {t('actions.signOut')}
        </DropdownMenuItem>
      </DropdownMenuContent>
    </DropdownMenu>
  )
}
