import { useTranslation } from 'react-i18next'
import type { AuditEntry } from '../types'

/** One changed field: its value before and after ("—" when it had none). */
export function AuditDiff({ entry }: { entry: AuditEntry }) {
  const { t } = useTranslation('audit')
  const labels = t('fields', { returnObjects: true }) as Record<string, string>
  const keys = [...new Set([...Object.keys(entry.before ?? {}), ...Object.keys(entry.after ?? {})])]

  const show = (value: unknown) => {
    if (value === null || value === undefined || value === '') return '—'
    if (typeof value === 'boolean') return value ? t('yes') : t('no')
    return typeof value === 'object' ? JSON.stringify(value) : String(value)
  }

  if (keys.length === 0) return <p className="text-sm text-muted-foreground">{t('noValues')}</p>

  return (
    <div className="overflow-x-auto rounded-xl border" data-lenis-prevent>
      <table className="w-full text-sm" aria-label={t('changes')}>
        <thead className="bg-muted/50 text-muted-foreground">
          <tr>
            <th className="px-3 py-2 text-start font-medium">{t('field')}</th>
            <th className="px-3 py-2 text-start font-medium">{t('before')}</th>
            <th className="px-3 py-2 text-start font-medium">{t('after')}</th>
          </tr>
        </thead>
        <tbody>
          {keys.map((key) => (
            <tr key={key} className="border-t align-top">
              <th scope="row" className="px-3 py-2 text-start font-medium">
                {labels[key] ?? key.replaceAll('_', ' ')}
              </th>
              <td className="px-3 py-2 break-words text-destructive" dir="auto">
                {show(entry.before?.[key])}
              </td>
              <td className="px-3 py-2 break-words text-primary" dir="auto">
                {show(entry.after?.[key])}
              </td>
            </tr>
          ))}
        </tbody>
      </table>
    </div>
  )
}
