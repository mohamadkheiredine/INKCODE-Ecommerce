import { useTranslation } from 'react-i18next'
import { InlineSpinner } from '@/components/feedback/inline-spinner'
import {
  AlertDialog,
  AlertDialogAction,
  AlertDialogCancel,
  AlertDialogContent,
  AlertDialogDescription,
  AlertDialogFooter,
  AlertDialogHeader,
  AlertDialogTitle,
} from '@/components/ui/alert-dialog'
import { useDirection } from '@/components/ui/direction'

type ConfirmDeleteDialogProps = {
  /** The record's name, or null when the dialog is closed. */
  name: string | null
  description: string
  pending: boolean
  onConfirm: () => void
  onCancel: () => void
}

/**
 * "Delete Shirts?" before anything is deleted. It stays open while the request runs and
 * closes once the caller says so (after success or a refusal it has explained).
 */
export function ConfirmDeleteDialog({
  name,
  description,
  pending,
  onConfirm,
  onCancel,
}: ConfirmDeleteDialogProps) {
  const { t } = useTranslation()
  const dir = useDirection()

  return (
    <AlertDialog open={name !== null} onOpenChange={(open) => !open && !pending && onCancel()}>
      <AlertDialogContent dir={dir} data-lenis-prevent>
        <AlertDialogHeader>
          <AlertDialogTitle>{t('confirmDelete.title', { name })}</AlertDialogTitle>
          <AlertDialogDescription>{description}</AlertDialogDescription>
        </AlertDialogHeader>
        <AlertDialogFooter>
          <AlertDialogCancel disabled={pending}>{t('actions.cancel')}</AlertDialogCancel>
          <AlertDialogAction
            variant="destructive"
            disabled={pending}
            onClick={(event) => {
              event.preventDefault()
              onConfirm()
            }}
            className="gap-2"
          >
            {pending && <InlineSpinner className="size-4" />}
            {t('actions.delete')}
          </AlertDialogAction>
        </AlertDialogFooter>
      </AlertDialogContent>
    </AlertDialog>
  )
}
