import { zodResolver } from '@hookform/resolvers/zod'
import { BoxesIcon, CoinsIcon, StoreIcon, UserPlusIcon } from 'lucide-react'
import { useRef } from 'react'
import { useForm, useWatch } from 'react-hook-form'
import { useTranslation } from 'react-i18next'
import { toast } from 'sonner'
import { InlineSpinner } from '@/components/feedback/inline-spinner'
import { SelectField, TextareaField, TextField } from '@/components/forms/form-fields'
import { Button } from '@/components/ui/button'
import { apiErrorMessage, showServerErrors } from '@/lib/forms'
import i18n, { LANGUAGES } from '@/lib/i18n'
import { useEntranceAnimation } from '@/lib/motion'
import { useSaveStoreSettings } from '../hooks/use-settings'
import {
  type SettingsFormValues,
  settingsFormValues,
  settingsSchema,
  toSettingsInput,
} from '../schemas/settings-schema'
import type { StoreSettings } from '../types'
import { DeductionStageField } from './deduction-stage-field'
import { MoneyPreview } from './money-preview'
import { SettingsSection } from './settings-section'
import { StoreLogoField } from './store-logo-field'

/** Every field the API may answer a 422 about; they share the form's names. */
const FIELDS = [
  'name',
  'contact_email',
  'contact_phone',
  'address',
  'currency_code',
  'currency_symbol',
  'vat_rate',
  'deduction_stage',
  'return_window_days',
  'low_stock_threshold',
  'default_locale',
  'default_theme',
] as const satisfies readonly (keyof SettingsFormValues)[]

/** All settings in one form, saved together; the logo saves on its own as soon as it is chosen. */
export function SettingsForm({ settings }: { settings: StoreSettings }) {
  const { t } = useTranslation('settings')
  const container = useRef<HTMLFormElement>(null)
  const save = useSaveStoreSettings()
  const form = useForm<SettingsFormValues>({
    resolver: zodResolver(settingsSchema),
    defaultValues: settingsFormValues(settings),
  })
  const { control } = form
  const returnWindowDays = useWatch({ control, name: 'return_window_days' })

  useEntranceAnimation(container, [{ targets: '.js-reveal', stagger: 0.06 }])

  const submit = async (values: SettingsFormValues) => {
    try {
      const saved = await save.mutateAsync(toSettingsInput(values))
      form.reset(settingsFormValues(saved))
      // i18n.t, not t: the language may have switched while the request ran.
      toast.success(i18n.t('settings:saved'))
    } catch (error) {
      if (!showServerErrors(form.setError, error, FIELDS)) toast.error(apiErrorMessage(error))
    }
  }

  const busy = save.isPending

  return (
    <form
      ref={container}
      noValidate
      onSubmit={form.handleSubmit(submit)}
      className="flex flex-col gap-8"
    >
      <fieldset disabled={busy} className="contents">
        <SettingsSection
          icon={StoreIcon}
          title={t('sections.profile.title')}
          description={t('sections.profile.description')}
        >
          <StoreLogoField storeName={settings.name} logoUrl={settings.logo_url} />
          <TextField
            control={control}
            name="name"
            idPrefix="settings"
            label={t('fields.name')}
            maxLength={120}
            autoComplete="organization"
          />
          <div className="grid gap-5 sm:grid-cols-2">
            <TextField
              control={control}
              name="contact_email"
              idPrefix="settings"
              label={t('fields.contactEmail')}
              type="email"
              dir="ltr"
              className="text-start"
              maxLength={255}
              autoComplete="email"
            />
            <TextField
              control={control}
              name="contact_phone"
              idPrefix="settings"
              label={t('fields.contactPhone')}
              type="tel"
              dir="ltr"
              className="text-start"
              maxLength={40}
              autoComplete="tel"
            />
          </div>
          <TextareaField
            control={control}
            name="address"
            idPrefix="settings"
            label={t('fields.address')}
            maxLength={500}
            rows={3}
            autoComplete="street-address"
          />
        </SettingsSection>

        <SettingsSection
          icon={CoinsIcon}
          title={t('sections.money.title')}
          description={t('sections.money.description')}
        >
          <div className="grid gap-5 sm:grid-cols-2">
            <TextField
              control={control}
              name="currency_code"
              idPrefix="settings"
              label={t('fields.currencyCode')}
              description={t('hints.currencyCode')}
              maxLength={3}
              dir="ltr"
              className="text-start uppercase"
              autoComplete="off"
            />
            <TextField
              control={control}
              name="currency_symbol"
              idPrefix="settings"
              label={t('fields.currencySymbol')}
              description={t('hints.currencySymbol')}
              maxLength={8}
              autoComplete="off"
            />
          </div>
          <TextField
            control={control}
            name="vat_rate"
            idPrefix="settings"
            label={t('fields.vatRate')}
            description={t('hints.vatRate')}
            unit={t('units.percent')}
            inputMode="decimal"
            dir="ltr"
            className="text-start tabular-nums"
            autoComplete="off"
          />
          <MoneyPreview control={control} />
        </SettingsSection>

        <SettingsSection
          icon={BoxesIcon}
          title={t('sections.stock.title')}
          description={t('sections.stock.description')}
        >
          <DeductionStageField control={control} />
          <div className="grid gap-5 sm:grid-cols-2">
            <TextField
              control={control}
              name="return_window_days"
              idPrefix="settings"
              label={t('fields.returnWindow')}
              description={t('hints.returnWindow')}
              unit={t('units.days', { count: Number(returnWindowDays) || 0 })}
              inputMode="numeric"
              dir="ltr"
              className="text-start tabular-nums"
              autoComplete="off"
            />
            <TextField
              control={control}
              name="low_stock_threshold"
              idPrefix="settings"
              label={t('fields.lowStockThreshold')}
              description={t('hints.lowStockThreshold')}
              inputMode="numeric"
              dir="ltr"
              className="text-start tabular-nums"
              autoComplete="off"
            />
          </div>
        </SettingsSection>

        <SettingsSection
          icon={UserPlusIcon}
          title={t('sections.defaults.title')}
          description={t('sections.defaults.description')}
        >
          <div className="grid gap-5 sm:grid-cols-2">
            <SelectField
              control={control}
              name="default_locale"
              idPrefix="settings"
              label={t('fields.defaultLanguage')}
              options={LANGUAGES.map((language) => ({
                value: language,
                label: t(`common:language.${language}`),
              }))}
            />
            <SelectField
              control={control}
              name="default_theme"
              idPrefix="settings"
              label={t('fields.defaultTheme')}
              options={(['light', 'dark'] as const).map((theme) => ({
                value: theme,
                label: t(`profile:preferences.${theme}`),
              }))}
            />
          </div>
        </SettingsSection>

        <SaveBar
          dirty={form.formState.isDirty}
          busy={busy}
          onDiscard={() => form.reset(settingsFormValues(settings))}
        />
      </fieldset>
    </form>
  )
}

/** Ends the form; while there are changes to save it stays in view at the bottom. */
function SaveBar({
  dirty,
  busy,
  onDiscard,
}: {
  dirty: boolean
  busy: boolean
  onDiscard: () => void
}) {
  const { t } = useTranslation('settings')

  return (
    <div
      className="flex flex-wrap items-center justify-between gap-3 rounded-2xl border bg-card px-4 py-3 data-[dirty=true]:sticky data-[dirty=true]:bottom-4 data-[dirty=true]:z-10 data-[dirty=true]:bg-background/90 data-[dirty=true]:shadow-lg data-[dirty=true]:backdrop-blur"
      data-dirty={dirty}
    >
      <p role="status" className="text-sm text-muted-foreground">
        {dirty ? t('unsaved') : ''}
      </p>
      <div className="flex gap-2">
        <Button type="button" variant="ghost" disabled={!dirty} onClick={onDiscard}>
          {t('discard')}
        </Button>
        <Button type="submit" disabled={!dirty} className="gap-2">
          {busy && <InlineSpinner className="size-4" />}
          {t('common:actions.saveChanges')}
        </Button>
      </div>
    </div>
  )
}
