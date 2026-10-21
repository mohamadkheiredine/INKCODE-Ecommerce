import { PlusIcon } from 'lucide-react'
import { useTranslation } from 'react-i18next'
import { Link } from 'react-router'
import { Button } from '@/components/ui/button'
import { useDirection } from '@/components/ui/direction'
import {
  DropdownMenu,
  DropdownMenuContent,
  DropdownMenuItem,
  DropdownMenuLabel,
  DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu'
import { useQuickActions } from '../hooks/use-quick-actions'

/** The header's "+" menu: the forms the User's Role may open. Hidden when it may open none. */
export function QuickActionsMenu() {
  const { t } = useTranslation('search')
  const dir = useDirection()
  const actions = useQuickActions()

  if (actions.length === 0) return null

  return (
    <DropdownMenu dir={dir}>
      <DropdownMenuTrigger asChild>
        <Button
          type="button"
          variant="ghost"
          size="icon"
          className="size-9 rounded-full"
          aria-label={t('actions.menu')}
        >
          <PlusIcon className="size-4" aria-hidden="true" />
        </Button>
      </DropdownMenuTrigger>
      <DropdownMenuContent align="end" className="w-52" data-lenis-prevent>
        <DropdownMenuLabel className="text-xs text-muted-foreground">
          {t('actions.menu')}
        </DropdownMenuLabel>
        {actions.map((action) => (
          <DropdownMenuItem key={action.id} asChild>
            <Link to={action.to} className="gap-2">
              <action.icon className="size-4" aria-hidden="true" />
              {t(`actions.${action.id}`)}
            </Link>
          </DropdownMenuItem>
        ))}
      </DropdownMenuContent>
    </DropdownMenu>
  )
}
