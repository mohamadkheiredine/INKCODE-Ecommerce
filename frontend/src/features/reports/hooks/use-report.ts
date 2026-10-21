import { keepPreviousData, useMutation, useQuery } from '@tanstack/react-query'
import { useTranslation } from 'react-i18next'
import { toast } from 'sonner'
import { reportKeys, reportsApi } from '../api/reports-api'
import type { ExportFormat, ReportName, ReportParams } from '../types'

export function useReport(name: ReportName, params: ReportParams) {
  return useQuery({
    queryKey: reportKeys.show(name, params),
    queryFn: () => reportsApi.show(name, params),
    placeholderData: keepPreviousData,
  })
}

/** Downloads a report file, with a toast when it is ready or when it fails. */
export function useExportReport(name: ReportName, params: ReportParams) {
  const { t } = useTranslation('reports')
  return useMutation({
    mutationFn: (format: ExportFormat) => reportsApi.download(name, params, format),
    onSuccess: () => toast.success(t('export.done')),
    onError: () => toast.error(t('export.failed')),
  })
}
