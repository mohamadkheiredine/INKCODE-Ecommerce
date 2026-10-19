import { EllipsisIcon, PencilIcon, Trash2Icon } from 'lucide-react'
import { useTranslation } from 'react-i18next'
import { Button } from '@/components/ui/button'
import {
  DropdownMenu,
  DropdownMenuContent,
  DropdownMenuItem,
  DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu'
import { useDirection } from '@/components/ui/direction'

/** Edit and Delete for one row, behind a "…" menu named after the record. */
export function RowActions({
  name,
  onEdit,
  onDelete,
}: {
  name: string
  onEdit: () => void
  onDelete: () => void
}) {
  const { t } = useTranslation()
  const dir = useDirection()

  return (
    <DropdownMenu dir={dir}>
      <DropdownMenuTrigger asChild>
        <Button
          type="button"
          variant="ghost"
          size="icon-sm"
          aria-label={t('dataTable.actionsFor', { name })}
        >
          <EllipsisIcon className="size-4" aria-hidden="true" />
        </Button>
      </DropdownMenuTrigger>
      <DropdownMenuContent align="end" className="w-40">
        <DropdownMenuItem onSelect={onEdit} className="gap-2">
          <PencilIcon className="size-4" aria-hidden="true" />
          {t('actions.edit')}
        </DropdownMenuItem>
        <DropdownMenuItem variant="destructive" onSelect={onDelete} className="gap-2">
          <Trash2Icon className="size-4" aria-hidden="true" />
          {t('actions.delete')}
        </DropdownMenuItem>
      </DropdownMenuContent>
    </DropdownMenu>
  )
}
