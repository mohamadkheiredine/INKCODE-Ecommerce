import { type Control, Controller } from 'react-hook-form'
import { useTranslation } from 'react-i18next'
import { FieldDescription, FieldLegend, FieldSet } from '@/components/ui/field'
import { Label } from '@/components/ui/label'
import { RadioGroup, RadioGroupItem } from '@/components/ui/radio-group'
import type { SettingsFormValues } from '../schemas/settings-schema'
import { DEDUCTION_STAGES } from '../types'

/** The three Order statuses stock can leave On-hand at, in flow order, each explained. */
export function DeductionStageField({ control }: { control: Control<SettingsFormValues> }) {
  const { t } = useTranslation('settings')

  return (
    <Controller
      name="deduction_stage"
      control={control}
      render={({ field }) => (
        <FieldSet className="gap-3">
          <FieldLegend variant="label" className="mb-0">
            {t('fields.deductionStage')}
          </FieldLegend>
          <FieldDescription id="settings-deduction-stage-description">
            {t('hints.deductionStage')}
          </FieldDescription>
          <RadioGroup
            value={field.value}
            onValueChange={field.onChange}
            aria-describedby="settings-deduction-stage-description"
            className="grid gap-2 md:grid-cols-3"
          >
            {DEDUCTION_STAGES.map((stage, index) => (
              <Label
                key={stage}
                htmlFor={`deduction-stage-${stage}`}
                className="flex cursor-pointer items-start gap-3 rounded-xl border bg-background p-3 font-normal transition-colors hover:bg-accent has-data-[state=checked]:border-primary has-data-[state=checked]:bg-primary/5"
              >
                <RadioGroupItem
                  id={`deduction-stage-${stage}`}
                  value={stage}
                  aria-describedby={`deduction-stage-${stage}-description`}
                  className="mt-0.5"
                />
                <span className="flex flex-col gap-1">
                  <span className="flex items-center gap-2 font-medium">
                    <span
                      aria-hidden="true"
                      className="flex size-5 items-center justify-center rounded-full bg-muted text-[0.7rem] text-muted-foreground tabular-nums"
                    >
                      {index + 1}
                    </span>
                    {t(`deductionStages.${stage}.label`)}
                  </span>
                  <span
                    id={`deduction-stage-${stage}-description`}
                    className="text-xs leading-relaxed text-muted-foreground"
                  >
                    {t(`deductionStages.${stage}.description`)}
                  </span>
                </span>
              </Label>
            ))}
          </RadioGroup>
        </FieldSet>
      )}
    />
  )
}
