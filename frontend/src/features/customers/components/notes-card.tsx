import { zodResolver } from '@hookform/resolvers/zod'
import { MessageSquareTextIcon } from 'lucide-react'
import { useForm } from 'react-hook-form'
import { useTranslation } from 'react-i18next'
import { toast } from 'sonner'
import { z } from 'zod'
import { ErrorState } from '@/components/feedback/error-state'
import { InlineSpinner } from '@/components/feedback/inline-spinner'
import { TextareaField } from '@/components/forms/form-fields'
import { Button } from '@/components/ui/button'
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card'
import { Skeleton } from '@/components/ui/skeleton'
import { UserAvatar } from '@/features/auth/components/user-avatar'
import { useFormatters } from '@/lib/format'
import { apiErrorMessage, showServerErrors } from '@/lib/forms'
import i18n from '@/lib/i18n'
import { useAddNote, useCustomerNotes } from '../hooks/use-customers'
import type { CustomerNote } from '../types'

/** The API's limit (CustomerNote::MAX_LENGTH). */
const NOTE_MAX_LENGTH = 2000

const noteSchema = z.object({
  body: z
    .string()
    .trim()
    .min(1, 'customers:validation.noteRequired')
    .max(NOTE_MAX_LENGTH, 'customers:validation.noteTooLong'),
})

type NoteFormValues = z.infer<typeof noteSchema>

/** Internal notes on a Customer, newest first; Users who manage Customers can add one. */
export function NotesCard({ customerId, canManage }: { customerId: number; canManage: boolean }) {
  const { t } = useTranslation('customers')
  const notes = useCustomerNotes(customerId)
  const all = notes.data?.pages.flatMap((page) => page.data) ?? []

  return (
    <Card className="js-reveal">
      <CardHeader>
        <CardTitle>{t('notes.title')}</CardTitle>
        <CardDescription>{t('notes.description')}</CardDescription>
      </CardHeader>
      <CardContent className="flex flex-col gap-5">
        {canManage && <NoteComposer customerId={customerId} />}
        {notes.isPending ? (
          <NotesSkeleton />
        ) : notes.isError ? (
          <ErrorState
            onRetry={() => void notes.refetch()}
            retrying={notes.isFetching}
            title={t('notes.errorTitle')}
            className="py-6"
          />
        ) : all.length === 0 ? (
          <div className="flex flex-col items-center gap-2 rounded-xl border border-dashed px-4 py-8 text-center">
            <MessageSquareTextIcon className="size-5 text-muted-foreground" aria-hidden="true" />
            <p className="text-sm text-muted-foreground">
              {canManage ? t('notes.empty') : t('notes.emptyReadOnly')}
            </p>
          </div>
        ) : (
          <>
            <ol className="flex flex-col" aria-label={t('notes.title')}>
              {all.map((note) => (
                <NoteItem key={note.id} note={note} />
              ))}
            </ol>
            {notes.hasNextPage && (
              <Button
                type="button"
                variant="ghost"
                size="sm"
                className="gap-2 self-center"
                disabled={notes.isFetchingNextPage}
                onClick={() => void notes.fetchNextPage()}
              >
                {notes.isFetchingNextPage && <InlineSpinner className="size-4" />}
                {t('notes.older')}
              </Button>
            )}
          </>
        )}
      </CardContent>
    </Card>
  )
}

function NoteComposer({ customerId }: { customerId: number }) {
  const { t } = useTranslation('customers')
  const addNote = useAddNote(customerId)
  const form = useForm<NoteFormValues>({
    resolver: zodResolver(noteSchema),
    defaultValues: { body: '' },
  })

  const submit = async ({ body }: NoteFormValues) => {
    try {
      await addNote.mutateAsync(body)
      form.reset()
      toast.success(i18n.t('customers:notes.added'))
    } catch (error) {
      if (!showServerErrors(form.setError, error, ['body'])) toast.error(apiErrorMessage(error))
    }
  }

  return (
    <form noValidate onSubmit={form.handleSubmit(submit)} className="flex flex-col gap-2">
      <fieldset disabled={addNote.isPending} className="contents">
        <TextareaField
          control={form.control}
          name="body"
          idPrefix="note"
          label={t('notes.new')}
          placeholder={t('notes.placeholder')}
          maxLength={NOTE_MAX_LENGTH}
          className="min-h-20"
          dir="auto"
        />
        <Button type="submit" size="sm" className="gap-2 self-end">
          {addNote.isPending && <InlineSpinner className="size-4" />}
          {t('notes.add')}
        </Button>
      </fieldset>
    </form>
  )
}

/** One note on the timeline: who wrote it, when, and what it says. */
function NoteItem({ note }: { note: CustomerNote }) {
  const { t } = useTranslation('customers')
  const { dateTime, relativeTime } = useFormatters()
  const author = note.author?.name ?? t('notes.formerUser')

  return (
    <li className="relative flex gap-3 pb-5 last:pb-0 [&:not(:last-child)]:before:absolute [&:not(:last-child)]:before:start-4 [&:not(:last-child)]:before:top-9 [&:not(:last-child)]:before:bottom-1 [&:not(:last-child)]:before:w-px [&:not(:last-child)]:before:bg-border">
      <UserAvatar name={author} className="size-8 shrink-0" fallbackClassName="text-xs" />
      <div className="flex min-w-0 flex-1 flex-col gap-1">
        <p className="flex flex-wrap items-baseline gap-x-2 text-sm">
          <span className="font-medium">{author}</span>
          <time
            dateTime={note.created_at}
            title={dateTime(note.created_at)}
            className="text-xs text-muted-foreground"
          >
            {relativeTime(note.created_at)}
          </time>
        </p>
        <p className="text-sm break-words whitespace-pre-line text-foreground/90" dir="auto">
          {note.body}
        </p>
      </div>
    </li>
  )
}

function NotesSkeleton() {
  return (
    <div className="flex flex-col gap-4" aria-hidden="true">
      {[0, 1].map((key) => (
        <div key={key} className="flex gap-3">
          <Skeleton className="size-8 rounded-full" />
          <div className="flex flex-1 flex-col gap-2">
            <Skeleton className="h-4 w-1/3" />
            <Skeleton className="h-4 w-full" />
          </div>
        </div>
      ))}
    </div>
  )
}
