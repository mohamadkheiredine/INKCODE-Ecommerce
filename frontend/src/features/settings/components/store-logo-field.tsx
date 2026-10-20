import { ImageUpIcon, XIcon } from 'lucide-react'
import { useRef, useState } from 'react'
import { useTranslation } from 'react-i18next'
import { toast } from 'sonner'
import { InlineSpinner } from '@/components/feedback/inline-spinner'
import { LogoTile } from '@/components/media/logo-tile'
import { Button } from '@/components/ui/button'
import { Field, FieldDescription, FieldError, FieldLabel } from '@/components/ui/field'
import { apiErrorMessage } from '@/lib/forms'
import i18n from '@/lib/i18n'
import { LOGO_TYPES, isLogoSize, isLogoType } from '@/lib/logo-file'
import { useChangeStoreLogo } from '../hooks/use-settings'

/**
 * The store's logo. It is uploaded (or removed) as soon as it is chosen, on its own
 * endpoint, so it never waits for the rest of the form.
 */
export function StoreLogoField({
  storeName,
  logoUrl,
}: {
  storeName: string
  logoUrl: string | null
}) {
  const { t } = useTranslation('settings')
  const input = useRef<HTMLInputElement>(null)
  const changeLogo = useChangeStoreLogo()
  const [error, setError] = useState<string | null>(null)

  const change = (logo: File | null) => {
    if (logo && !isLogoType(logo)) return setError(t('logo.type'))
    if (logo && !isLogoSize(logo)) return setError(t('logo.tooLarge'))
    setError(null)
    changeLogo.mutate(logo, {
      // i18n.t, not t: the language may have switched while the upload ran.
      onSuccess: () =>
        toast.success(i18n.t(logo ? 'settings:logo.saved' : 'settings:logo.removed')),
      onError: (failure) => setError(apiErrorMessage(failure)),
    })
  }

  const busy = changeLogo.isPending

  return (
    <Field data-invalid={error !== null}>
      <FieldLabel htmlFor="settings-logo">{t('fields.logo')}</FieldLabel>
      <div className="flex items-center gap-4">
        <LogoTile
          name={storeName}
          src={logoUrl}
          alt={t('logo.alt', { name: storeName })}
          className="size-16 rounded-2xl text-base"
        />
        <div className="flex flex-wrap gap-2">
          <Button
            type="button"
            variant="outline"
            size="sm"
            className="gap-2"
            disabled={busy}
            onClick={() => input.current?.click()}
          >
            {busy ? (
              <InlineSpinner className="size-4" />
            ) : (
              <ImageUpIcon className="size-4" aria-hidden="true" />
            )}
            {logoUrl ? t('logo.replace') : t('logo.choose')}
          </Button>
          {logoUrl && (
            <Button
              type="button"
              variant="ghost"
              size="sm"
              className="gap-2"
              disabled={busy}
              onClick={() => change(null)}
            >
              <XIcon className="size-4" aria-hidden="true" />
              {t('logo.remove')}
            </Button>
          )}
        </div>
      </div>
      <input
        ref={input}
        id="settings-logo"
        type="file"
        accept={LOGO_TYPES.join(',')}
        tabIndex={-1}
        className="sr-only"
        aria-invalid={error !== null}
        aria-describedby={error ? 'settings-logo-error' : 'settings-logo-description'}
        onChange={(event) => {
          const file = event.target.files?.[0]
          event.target.value = ''
          if (file) change(file)
        }}
      />
      <FieldDescription id="settings-logo-description">{t('hints.logo')}</FieldDescription>
      <FieldError id="settings-logo-error">{error}</FieldError>
    </Field>
  )
}
