import { useTranslation } from 'react-i18next'
import { Input } from '@/components/ui/input'
import { ToggleGroup, ToggleGroupItem } from '@/components/ui/toggle-group'
import { DATE_PRESETS, type DatePreset, type DateRange } from '../types'

type DateRangePickerProps = {
  preset: DatePreset
  range: DateRange
  onPresetChange: (preset: DatePreset) => void
  onCustomRangeChange: (range: DateRange) => void
}

/** The presets (today, 7 days, 30 days, this month, custom) and, for custom, its two days. */
export function DateRangePicker({
  preset,
  range,
  onPresetChange,
  onCustomRangeChange,
}: DateRangePickerProps) {
  const { t } = useTranslation('dashboard')

  return (
    <div className="flex flex-col items-start gap-3 sm:items-end">
      <ToggleGroup
        type="single"
        variant="outline"
        size="sm"
        spacing={0}
        value={preset}
        aria-label={t('range.label')}
        onValueChange={(value) => value && onPresetChange(value as DatePreset)}
        className="flex-wrap bg-card"
      >
        {DATE_PRESETS.map((item) => (
          <ToggleGroupItem key={item} value={item} className="px-3">
            {t(`range.presets.${item}`)}
          </ToggleGroupItem>
        ))}
      </ToggleGroup>
      {preset === 'custom' && (
        <div className="flex flex-wrap items-center gap-3">
          <label className="flex items-center gap-2 text-sm text-muted-foreground">
            {t('range.from')}
            <Input
              type="date"
              value={range.from}
              max={range.to}
              onChange={(event) =>
                event.target.value && onCustomRangeChange({ ...range, from: event.target.value })
              }
              className="h-9 w-40 bg-card"
            />
          </label>
          <label className="flex items-center gap-2 text-sm text-muted-foreground">
            {t('range.to')}
            <Input
              type="date"
              value={range.to}
              min={range.from}
              onChange={(event) =>
                event.target.value && onCustomRangeChange({ ...range, to: event.target.value })
              }
              className="h-9 w-40 bg-card"
            />
          </label>
        </div>
      )}
    </div>
  )
}
