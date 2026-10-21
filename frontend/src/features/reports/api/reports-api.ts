import { http } from '@/lib/api/client'
import type { ExportFormat, Report, ReportName, ReportParams } from '../types'

export const reportKeys = {
  all: ['reports'] as const,
  show: (name: ReportName, params: ReportParams) => [...reportKeys.all, name, params] as const,
}

/** The file name the API gave in Content-Disposition, or a fallback of the same shape. */
function fileName(disposition: unknown, fallback: string): string {
  const match = typeof disposition === 'string' ? /filename="?([^";]+)"?/.exec(disposition) : null
  return match?.[1] ?? fallback
}

export const reportsApi = {
  async show(name: ReportName, params: ReportParams): Promise<Report> {
    const { data } = await http.get<{ data: Report }>(`/reports/${name}`, { params })
    return data.data
  },

  /** Downloads the report as CSV or Excel, named by the API after the report and its dates. */
  async download(name: ReportName, params: ReportParams, format: ExportFormat): Promise<void> {
    const response = await http.get<Blob>(`/reports/${name}/export`, {
      params: { ...params, format },
      responseType: 'blob',
    })
    const url = URL.createObjectURL(response.data)
    const link = document.createElement('a')
    link.href = url
    link.download = fileName(
      response.headers['content-disposition'],
      `${name}-report-${params.from}-to-${params.to}.${format}`,
    )
    document.body.append(link)
    link.click()
    link.remove()
    // Revoked a tick later: some browsers cancel a download whose URL is revoked at once.
    setTimeout(() => URL.revokeObjectURL(url), 0)
  },
}
