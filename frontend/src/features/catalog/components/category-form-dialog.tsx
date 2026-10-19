import { zodResolver } from '@hookform/resolvers/zod'
import { TriangleAlertIcon } from 'lucide-react'
import { useState } from 'react'
import { useForm } from 'react-hook-form'
import { useTranslation } from 'react-i18next'
import { toast } from 'sonner'
import { InlineSpinner } from '@/components/feedback/inline-spinner'
import { SelectField, TextareaField, TextField } from '@/components/forms/form-fields'
import { Alert, AlertDescription } from '@/components/ui/alert'
import { Button } from '@/components/ui/button'
import {
  Dialog,
  DialogContent,
  DialogDescription,
  DialogFooter,
  DialogHeader,
  DialogTitle,
} from '@/components/ui/dialog'
import { useDirection } from '@/components/ui/direction'
import { FieldGroup } from '@/components/ui/field'
import { apiErrorMessage, showServerErrors } from '@/lib/forms'
import i18n from '@/lib/i18n'
import { useParentCategories, useSaveCategory } from '../hooks/use-categories'
import {
  type CategoryFormValues,
  categoryFormValues,
  categorySchema,
  toCategoryInput,
} from '../schemas/category-schema'
import type { Category } from '../types'

const FIELDS = ['name', 'slug', 'parent_id', 'description'] as const

type CategoryFormDialogProps = {
  open: boolean
  /** The Category to edit, or null to create one. */
  category: Category | null
  onOpenChange: (open: boolean) => void
}

/** Creates or edits a Category: name, optional slug, optional top-level parent, description. */
export function CategoryFormDialog({ open, category, onOpenChange }: CategoryFormDialogProps) {
  const { t } = useTranslation('catalog')
  const dir = useDirection()

  return (
    <Dialog open={open} onOpenChange={onOpenChange}>
      <DialogContent
        dir={dir}
        className="max-h-[90svh] overflow-y-auto sm:max-w-lg"
        data-lenis-prevent
      >
        <DialogHeader>
          <DialogTitle>
            {category ? t('categories.editTitle') : t('categories.newTitle')}
          </DialogTitle>
          <DialogDescription>{t('categories.formDescription')}</DialogDescription>
        </DialogHeader>
        {/* Mounted per opening, so the form always starts from the Category's current values. */}
        {open && <CategoryForm category={category} onDone={() => onOpenChange(false)} />}
      </DialogContent>
    </Dialog>
  )
}

function CategoryForm({ category, onDone }: { category: Category | null; onDone: () => void }) {
  const { t } = useTranslation('catalog')
  const save = useSaveCategory()
  const parents = useParentCategories(true)
  const [formError, setFormError] = useState<string | null>(null)
  const form = useForm<CategoryFormValues>({
    resolver: zodResolver(categorySchema),
    defaultValues: categoryFormValues(category),
  })

  const hasChildren = (category?.children_count ?? 0) > 0
  const parentOptions = [
    { value: '', label: t('categories.noParent') },
    ...(parents.data ?? [])
      .filter((parent) => parent.id !== category?.id)
      .map((parent) => ({ value: String(parent.id), label: parent.name })),
  ]

  const submit = async (values: CategoryFormValues) => {
    setFormError(null)
    try {
      await save.mutateAsync({ id: category?.id ?? null, input: toCategoryInput(values) })
      // i18n.t, not t: the language may have switched while the request ran.
      toast.success(i18n.t(category ? 'catalog:categories.saved' : 'catalog:categories.created'))
      onDone()
    } catch (error) {
      if (!showServerErrors(form.setError, error, FIELDS)) setFormError(apiErrorMessage(error))
    }
  }

  const busy = save.isPending

  return (
    <form noValidate onSubmit={form.handleSubmit(submit)} className="flex flex-col gap-6">
      {formError && (
        <Alert variant="destructive" role="alert">
          <TriangleAlertIcon aria-hidden="true" />
          <AlertDescription>{formError}</AlertDescription>
        </Alert>
      )}
      <fieldset disabled={busy} className="contents">
        <FieldGroup className="gap-4">
          <TextField
            control={form.control}
            name="name"
            idPrefix="category"
            label={t('fields.name')}
            maxLength={121}
            autoComplete="off"
          />
          <TextField
            control={form.control}
            name="slug"
            idPrefix="category"
            label={t('fields.slug')}
            description={t('fields.slugHint')}
            maxLength={141}
            autoComplete="off"
            dir="ltr"
            className="text-start"
          />
          <SelectField
            control={form.control}
            name="parent_id"
            idPrefix="category"
            label={t('categories.parent')}
            options={parentOptions}
            disabled={hasChildren || parents.isPending}
            description={
              hasChildren
                ? t('categories.parentLocked', { name: category?.name })
                : t('categories.parentHint')
            }
          />
          <TextareaField
            control={form.control}
            name="description"
            idPrefix="category"
            label={t('fields.description')}
            maxLength={2001}
          />
        </FieldGroup>
        <DialogFooter>
          <Button type="button" variant="outline" onClick={onDone}>
            {t('common:actions.cancel')}
          </Button>
          <Button type="submit" className="gap-2">
            {busy && <InlineSpinner className="size-4" />}
            {category ? t('common:actions.saveChanges') : t('categories.create')}
          </Button>
        </DialogFooter>
      </fieldset>
    </form>
  )
}
