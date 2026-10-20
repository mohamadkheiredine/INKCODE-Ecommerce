import { PlusIcon, XIcon } from 'lucide-react'
import { type KeyboardEvent, useState } from 'react'
import { type Control, Controller } from 'react-hook-form'
import { useTranslation } from 'react-i18next'
import { Badge } from '@/components/ui/badge'
import { Button } from '@/components/ui/button'
import { Field, FieldDescription, FieldError, FieldLabel } from '@/components/ui/field'
import { cn } from '@/lib/utils'
import { useCustomerTags } from '../hooks/use-customers'
import { type CustomerFormValues, MAX_TAGS, TAG_MAX_LENGTH } from '../schemas/customer-schema'

/** How many existing Tags are suggested under the box. */
const SUGGESTIONS = 6

const sameTag = (a: string, b: string) => a.toLocaleLowerCase() === b.toLocaleLowerCase()

/**
 * The Customer's Tags as removable chips, with a box to type a new one (Enter or comma
 * adds it) and the existing Tags that match what is typed, one click away. New names
 * become Tags when the Customer is saved.
 */
export function TagField({ control }: { control: Control<CustomerFormValues> }) {
  const { t } = useTranslation('customers')
  const tags = useCustomerTags()
  const [draft, setDraft] = useState('')

  return (
    <Controller
      name="tags"
      control={control}
      render={({ field, fieldState }) => {
        const chosen = field.value
        const add = (name: string) => {
          const tag = name.trim().slice(0, TAG_MAX_LENGTH)
          if (tag && chosen.length < MAX_TAGS && !chosen.some((c) => sameTag(c, tag))) {
            field.onChange([...chosen, tag])
          }
          setDraft('')
        }
        const remove = (tag: string) => field.onChange(chosen.filter((c) => c !== tag))
        const onKeyDown = (event: KeyboardEvent<HTMLInputElement>) => {
          if ((event.key === 'Enter' || event.key === ',') && draft.trim()) {
            event.preventDefault()
            add(draft)
          } else if (event.key === 'Enter') {
            event.preventDefault()
          } else if (event.key === 'Backspace' && !draft && chosen.length > 0) {
            remove(chosen.at(-1)!)
          }
        }
        const typed = draft.trim().toLocaleLowerCase()
        const suggestions = (tags.data ?? [])
          .filter((tag) => !chosen.some((c) => sameTag(c, tag.name)))
          .filter((tag) => tag.name.toLocaleLowerCase().includes(typed))
          .slice(0, SUGGESTIONS)

        return (
          <Field data-invalid={fieldState.invalid}>
            <FieldLabel htmlFor="customer-tags">{t('fields.tags')}</FieldLabel>
            <div
              className={cn(
                'flex min-h-10 flex-wrap items-center gap-1.5 rounded-lg border border-input bg-background px-2 py-1.5 transition-colors',
                'focus-within:border-ring focus-within:ring-3 focus-within:ring-ring/50 dark:bg-input/30',
              )}
            >
              {chosen.map((tag) => (
                <Badge key={tag} variant="secondary" className="gap-1 rounded-md pe-1 font-normal">
                  <span dir="auto">{tag}</span>
                  <button
                    type="button"
                    onClick={() => remove(tag)}
                    aria-label={t('tags.remove', { name: tag })}
                    className="rounded-sm p-0.5 text-muted-foreground hover:bg-muted hover:text-foreground focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none"
                  >
                    <XIcon className="size-3" aria-hidden="true" />
                  </button>
                </Badge>
              ))}
              <input
                id="customer-tags"
                value={draft}
                maxLength={TAG_MAX_LENGTH}
                onChange={(event) => setDraft(event.target.value)}
                onKeyDown={onKeyDown}
                onBlur={() => draft.trim() && add(draft)}
                placeholder={chosen.length === 0 ? t('tags.placeholder') : undefined}
                aria-describedby="customer-tags-description"
                autoComplete="off"
                className="h-7 min-w-28 flex-1 bg-transparent px-1 text-base outline-none placeholder:text-muted-foreground md:text-sm"
              />
            </div>
            {suggestions.length > 0 && (
              <div
                className="flex flex-wrap gap-1.5"
                aria-label={t('tags.suggestions')}
                role="group"
              >
                {suggestions.map((tag) => (
                  <Button
                    key={tag.id}
                    type="button"
                    variant="outline"
                    size="xs"
                    className="gap-1 rounded-md font-normal"
                    onMouseDown={(event) => event.preventDefault()}
                    onClick={() => add(tag.name)}
                  >
                    <PlusIcon className="size-3" aria-hidden="true" />
                    <span dir="auto">{tag.name}</span>
                  </Button>
                ))}
              </div>
            )}
            <FieldDescription id="customer-tags-description">{t('tags.hint')}</FieldDescription>
            <FieldError>
              {fieldState.error?.message && t(fieldState.error.message as never)}
            </FieldError>
          </Field>
        )
      }}
    />
  )
}
